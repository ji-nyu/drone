from djitellopy import Tello
from ultralytics import YOLO
import cv2
import time
import csv
from datetime import datetime

model = YOLO("best.pt")

csv_file = "detection_zone_log.csv"

with open(csv_file, "w", newline="", encoding="utf-8-sig") as f:
    writer = csv.writer(f)
    writer.writerow(["time", "zone", "class", "confidence", "x1", "y1", "x2", "y2"])

#tello = Tello()
tello = Tello(host="192.168.0.22")
tello.connect(wait_for_state=False)

tello.streamon()
time.sleep(2)

frame_read = tello.get_frame_read()

while True:
    frame = frame_read.frame

    if frame is None:
        continue

    frame = cv2.cvtColor(frame, cv2.COLOR_RGB2BGR)

    results = model(frame, conf=0.75)
    annotated = results[0].plot()

    boxes = results[0].boxes

    if boxes is not None:
        with open(csv_file, "a", newline="", encoding="utf-8-sig") as f:
            writer = csv.writer(f)

            for box in boxes:
                cls_id = int(box.cls[0])
                cls_name = model.names[cls_id]
                conf = float(box.conf[0])
                x1, y1, x2, y2 = map(int, box.xyxy[0])

                center_x = (x1 + x2) // 2
                frame_width = frame.shape[1]

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

    cv2.imshow("Tello YOLO Detection", annotated)

    if cv2.waitKey(1) & 0xFF == ord("q"):
        break

tello.streamoff()
tello.end()
cv2.destroyAllWindows()