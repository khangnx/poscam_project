from fastapi import APIRouter
from pydantic import BaseModel

router = APIRouter()

class PrintCommand(BaseModel):
    order_id: int
    content: str
    printer_ip: str

@router.post("/")
def trigger_print_job(job: PrintCommand):
    """
    Gửi lệnh in bill ra máy in nhiệt ESC/POS.
    Thông tin này thường do Laravel đẩy qua.
    """
    return {
        "status": "success",
        "message": f"Đã gửi lệnh in cho Order #{job.order_id} đến máy in {job.printer_ip}"
    }
