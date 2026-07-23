from djitellopy import Tello
from ultralytics import YOLO
import cv2
import time
import csv
from datetime import datetime

model = YOLO("best.pt")

csv_file = "detection_zone_log.csv"

# 설정값
CONF_THRESHOLD = 0.85
MAX_BOX_RATIO = 0.30
MIN_BOX_RATIO = 0.001

# YOLO 실행 간격
frame_count = 0
DETECT_EVERY = 5

with open(csv_file, "w", newline="", encoding="utf-8-sig") as f:
    writer = csv.writer(f)
    writer.writerow(["time", "zone", "class", "confidence", "x1", "y1", "x2", "y2"])

tello = Tello()
tello.connect(wait_for_state=False)

tello.streamon()
time.sleep(2)

frame_read = tello.get_frame_read()

while True:
    frame = frame_read.frame

    if frame is None:
        continue

    frame = cv2.cvtColor(frame, cv2.COLOR_RGB2BGR)

    frame_count += 1

    # 5프레임마다 1번만 YOLO 실행
    if frame_count % DETECT_EVERY != 0:
        cv2.imshow("Tello YOLO Detection", frame)

        if cv2.waitKey(1) & 0xFF == ord("q"):
            break

        continue

    results = model(
        frame,
        conf=CONF_THRESHOLD,
        imgsz=416,
        verbose=False
    )

    annotated = frame.copy()

    frame_height, frame_width = frame.shape[:2]
    frame_area = frame_width * frame_height

    boxes = results[0].boxes

    if boxes is not None:
        with open(csv_file, "a", newline="", encoding="utf-8-sig") as f:
            writer = csv.writer(f)

            for box in boxes:
                cls_id = int(box.cls[0])
                cls_name = model.names[cls_id]
                conf = float(box.conf[0])

                x1, y1, x2, y2 = map(int, box.xyxy[0])

                box_area = (x2 - x1) * (y2 - y1)
                box_ratio = box_area / frame_area

                if box_ratio > MAX_BOX_RATIO:
                    continue

                if box_ratio < MIN_BOX_RATIO:
                    continue

                center_x = (x1 + x2) // 2

                if center_x < frame_width / 3:
                    zone = "A1"
                elif center_x < frame_width * 2 / 3:
                    zone = "B1"
                else:
                    zone = "C1"

                writer.writerow([
                    datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
                    zone,
                    cls_name,
                    round(conf, 3),
                    x1, y1, x2, y2
                ])

                cv2.rectangle(
                    annotated,
                    (x1, y1),
                    (x2, y2),
                    (0, 255, 0),
                    2
                )

                label = f"{cls_name} {conf:.2f} {zone}"

                cv2.putText(
                    annotated,
                    label,
                    (x1, max(y1 - 10, 20)),
                    cv2.FONT_HERSHEY_SIMPLEX,
                    0.6,
                    (0, 255, 0),
                    2
                )

    cv2.imshow("Tello YOLO Detection", annotated)

    if cv2.waitKey(1) & 0xFF == ord("q"):
        break

tello.streamoff()
tello.end()
cv2.destroyAllWindows()