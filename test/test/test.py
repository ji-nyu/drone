from djitellopy import Tello
import cv2
import time
import threading

tello = Tello(host="192.168.10.1")

found_pad = False
current_status = "대기 중" 

def watch_pad(target_pad):
    global found_pad
    while not found_pad:
        if tello.get_mission_pad_id() == target_pad:
            found_pad = True
        time.sleep(0.1)

def cruise_until_pad(direction, target_pad, speed=15, max_cm=200):
    global found_pad, current_status
    found_pad = False
    current_status = f"패드{target_pad} 탐색 중..." 

    watcher = threading.Thread(target=watch_pad, args=(target_pad,))
    watcher.daemon = True
    watcher.start()

    rc = {
        "right":   (speed, 0, 0, 0),
        "forward": (0, speed, 0, 0),
        "left":    (-speed, 0, 0, 0),
    }
    tello.send_rc_control(*rc[direction])

    elapsed = 0
    interval = 0.1

    while not found_pad:
        time.sleep(interval)
        elapsed += interval
        moved_cm = speed * elapsed

        if moved_cm >= max_cm:
            tello.send_rc_control(0, 0, 0, 0)
            current_status = f":warning: 패드{target_pad} 못 찾음 (200cm 초과)" 
            return False

    tello.send_rc_control(0, 0, 0, 0)
    time.sleep(0.5)
    current_status = f"패드{target_pad} 인식! 보정 중..." 
    tello.go_xyz_speed_mid(0, 0, 80, 20, target_pad)
    time.sleep(1)
    current_status = f"패드{target_pad} 완료!" 
    return True

def draw_overlay(frame):
    """화면에 정보 오버레이""" 
    h, w = frame.shape[:2]

    # 배터리
    battery = tello.get_battery()
    pad_id = tello.get_mission_pad_id()

    # 반투명 상단 바
    overlay = frame.copy()
    cv2.rectangle(overlay, (0, 0), (w, 60), (0, 0, 0), -1)
    cv2.addWeighted(overlay, 0.5, frame, 0.5, 0, frame)

    # 텍스트
    cv2.putText(frame, f"Battery: {battery}%", (10, 25),
                cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
    cv2.putText(frame, f"Pad ID: {pad_id if pad_id != -1 else 'None'}",
                (10, 50), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 255), 2)

    # 상태 텍스트 (하단)
    cv2.rectangle(frame, (0, h-40), (w, h), (0, 0, 0), -1)
    cv2.putText(frame, current_status, (10, h-12),
                cv2.FONT_HERSHEY_SIMPLEX, 0.65, (255, 255, 255), 2)

    # 패드 인식됐을 때 중앙에 표시
    if pad_id != -1:
        cx, cy = w // 2, h // 2
        cv2.circle(frame, (cx, cy), 30, (0, 255, 0), 2)
        cv2.line(frame, (cx-40, cy), (cx+40, cy), (0, 255, 0), 1)
        cv2.line(frame, (cx, cy-40), (cx, cy+40), (0, 255, 0), 1)

    return frame

def mission():
    global current_status
    try:
        tello.connect()
        print("배터리:", tello.get_battery(), "%")

        tello.streamon()
        tello.enable_mission_pads()
        tello.set_mission_pad_detection_direction(0)

        current_status = "패드1 이륙..." 
        tello.takeoff()
        time.sleep(2)
        tello.go_xyz_speed_mid(0, 0, 80, 20, 1)
        time.sleep(1)

        if not cruise_until_pad("right", 2):
            raise Exception("패드2 탐색 실패")

        if not cruise_until_pad("forward", 3):
            raise Exception("패드3 탐색 실패")

        if not cruise_until_pad("left", 4):
            raise Exception("패드4 탐색 실패")

        current_status = "패드4 착륙!" 
        tello.land()
        current_status = "임무 완료!" 

    except Exception as e:
        current_status = f"오류: {e}" 
        print(f"오류: {e}")
        try:
            tello.send_rc_control(0, 0, 0, 0)
            time.sleep(0.3)
            tello.land()
        except:
            pass

    finally:
        tello.disable_mission_pads()
        tello.end()

# 미션 스레드 실행
mission_thread = threading.Thread(target=mission)
mission_thread.daemon = True
mission_thread.start()

# 잠깐 대기 후 스트림 시작
time.sleep(3)

# 메인 스레드에서 화면 표시
frame_reader = tello.get_frame_read()
while True:
    frame = frame_reader.frame
    if frame is not None:
        frame = draw_overlay(frame)
        cv2.imshow("Tello 드론 뷰", frame)

    # q 누르면 종료
    if cv2.waitKey(1) & 0xFF == ord('q'):
        print("화면 종료")
        break

cv2.destroyAllWindows()
