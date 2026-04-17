<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Hóa đơn #{{ $order->id }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 14px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 24px; color: #000; }
        .info { margin-bottom: 20px; }
        .info table { width: 100%; }
        .items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .items th { background: #f2f2f2; border: 1px solid #ddd; padding: 8px; text-align: left; }
        .items td { border: 1px solid #ddd; padding: 8px; }
        .total { text-align: right; font-size: 18px; font-weight: bold; }
        .footer { text-align: center; margin-top: 50px; font-size: 12px; color: #777; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $shop_name }}</h1>
        <p>{{ $address }}</p>
        <hr>
        <h2>HÓA ĐƠN BÁN LẺ</h2>
        <p>Mã đơn: #{{ $order->id }} | Ngày: {{ $order->created_at->format('d/m/Y H:i') }}</p>
    </div>

    <div class="info">
        <table>
            <tr>
                <td><strong>Khách hàng:</strong> {{ $order->customer_name ?: 'Khách lẻ' }}</td>
                <td style="text-align: right;"><strong>Nhân viên:</strong> {{ $order->user->name ?? 'Hệ thống' }}</td>
            </tr>
            <tr>
                <td><strong>Phương thức:</strong> {{ strtoupper($order->payment_method) }}</td>
                <td style="text-align: right;"><strong>Trạng thái:</strong> {{ strtoupper($order->status) }}</td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>Sản phẩm</th>
                <th style="text-align: center;">SL</th>
                <th style="text-align: right;">Đơn giá</th>
                <th style="text-align: right;">Thành tiền</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $item)
            <tr>
                <td>{{ $item->product->name ?? 'Sản phẩm đã xóa' }}</td>
                <td style="text-align: center;">{{ $item->quantity }}</td>
                <td style="text-align: right;">{{ number_format($item->price_at_purchase, 0, ',', '.') }} VNĐ</td>
                <td style="text-align: right;">{{ number_format($item->price_at_purchase * $item->quantity, 0, ',', '.') }} VNĐ</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        Tổng thanh toán: {{ number_format($order->total_amount, 0, ',', '.') }} VNĐ
    </div>

    <div class="footer">
        <p>Cảm ơn quý khách đã mua sắm tại cửa hàng!</p>
        <p>Hóa đơn được in lại vào lúc: {{ now()->format('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
