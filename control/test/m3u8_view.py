import cv2
import os

file_path = "/data/www/droneControl/public/stream/index.m3u8"
output_path = "snapshot.jpg"

cap = cv2.VideoCapture(file_path)

if cap.isOpened():
    ret, frame = cap.read()
    if ret:
        cv2.imwrite(output_path, frame)
        print(f"이미지 저장 완료: {os.path.abspath(output_path)}")
    else:
        print("프레임을 읽을 수 없습니다.")
else:
    print("스트림을 열 수 없습니다.")

cap.release()
