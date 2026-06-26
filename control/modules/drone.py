"""호환용 별칭. 새 코드는 `modules.ctrl`의 `DroneController` 사용을 권장."""

from modules.ctrl import DroneController

Drone = DroneController

__all__ = ["Drone", "DroneController"]
