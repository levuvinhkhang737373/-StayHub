<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Ghi lại snapshot trạng thái Laravel Horizon định kỳ
Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Kiểm tra và cập nhật các hợp đồng đã hết hạn
Schedule::command('contracts:check-expired')
    ->dailyAt('00:10')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

// Tự động hủy các khoản đặt cọc đã quá hạn
Schedule::command('contracts:cancel-expired-deposits')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Nhắc hóa đơn chưa thanh toán
Schedule::command('invoices:send-debt-reminders')
    ->monthlyOn(7, '07:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

// Thực hiện các yêu cầu chuyển phòng đã đến lịch
Schedule::command('room-transfers:execute-scheduled')
    ->dailyAt('00:10')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();

// Gửi thông báo các phòng sắp bị ngắt tiện ích
Schedule::command('room-transfers:notify-utility-cutoffs')
    ->dailyAt('07:00')
    ->timezone('Asia/Ho_Chi_Minh')
    ->withoutOverlapping();
