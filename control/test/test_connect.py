from djitellopy import Tello
import cv2
import time
import threading

#tello = Tello(host="172.16.0.59")
tello = Tello(host="192.168.0.22")
tello.connect(wait_for_state=True)
print(tello.get_battery())


tello.streamon()

# tello.end()
