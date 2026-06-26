from djitellopy import Tello
import cv2
import time

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

    cv2.imshow("Tello Live Camera", frame)

    if cv2.waitKey(1) & 0xFF == ord("q"):
        break

tello.streamoff()
tello.end()
cv2.destroyAllWindows()