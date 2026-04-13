import os
from typing import List
from pydantic import BaseModel
from escpos.printer import File as FilePrinter

# --- Pydantic Models cho Hóa Đơn ---
class PrintItem(BaseModel):
    name: str
    quantity: int
    price: float

class PrintJob(BaseModel):
    shop_name: str
    address: str = ""
    greeting: str = ""
    items: List[PrintItem]
    total: float
    note: str = None

class ThermalPrinterManager:
    def __init__(self):
        # Thiết bị USB thường được truyền vào Docker dưới dạng /dev/usb/lp0
        self.printer_path = os.getenv("PRINTER_PATH", "/dev/usb/lp0")

    def print_receipt(self, job: PrintJob) -> bool:
        """
        Thực hiện kết nối và in hóa đơn qua máy in nhiệt.
        """
        try:
            printer = FilePrinter(self.printer_path)
            
            # Tiêu đề
            printer.set(align='center', bold=True, width=2, height=2)
            printer.text(f"{job.shop_name}\n")
            if job.address:
                printer.set(align='center', bold=False, width=1, height=1)
                printer.text(f"{job.address}\n")
            
            printer.set(align='center', bold=False, width=1, height=1)
            printer.text("-" * 32 + "\n")
            
            # Chi tiết món
            printer.set(align='left')
            printer.text(f"{'Ten hang':<18} {'SL':<2} {'Gia':>9}\n")
            printer.text("-" * 32 + "\n")
            for item in job.items:
                line = f"{item.name[:18]:<18} x{item.quantity:<2} {item.price:>9.0f}\n"
                printer.text(line)
                
            printer.text("-" * 32 + "\n")
            
            # Tổng kết
            printer.set(align='right', bold=True, width=1, height=2)
            printer.text(f"Total: {job.total:.0f} VND\n")
            
            # Khuyến mãi Voucher Đua xe (note)
            if job.note:
                printer.set(align='center', bold=False, width=1, height=1)
                printer.text("-" * 32 + "\n")
                printer.text(f"{job.note}\n")
                printer.text("-" * 32 + "\n\n")

            # Lời chào
            if job.greeting:
                printer.set(align='center', bold=False, width=1, height=1)
                printer.text(job.greeting + "\n")
                
            printer.text("\n\n\n\n")
            printer.cut()
            printer.close()
            return True
        except Exception as e:
            # Re-raise error để router có thể catch xử lý
            raise e
