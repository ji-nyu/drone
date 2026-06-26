from djitellopy import Tello
import cv2

tello = Tello()
tello.connect()
tello.streamon()

frame_read = tello.get_frame_read()

"""
try:
    while True:
        frame = frame_read.frame
        if frame is not None:
            cv2.imshow("Tello", frame)
        if cv2.waitKey(1) & 0xFF == ord("q"):
            break
finally:
    cv2.destroyAllWindows()
    tello.streamoff()
    tello.end()
"""
try:
    while True:
        frame = frame_read.frame
        if frame is not None:
            cv2.imshow("Tello", frame)
        if cv2.waitKey(1) & 0xFF == ord("q"):
            break
finally:
    cv2.destroyAllWindows()
    tello.streamoff()
    tello.end()