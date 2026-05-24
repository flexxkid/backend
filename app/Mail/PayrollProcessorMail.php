<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\View;

class PayrollProcessorMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employeeName;
    public $payPeriod;
    public $netPay;

    public function __construct($employeeName, $payPeriod, $netPay)
    {
        $this->employeeName = $employeeName;
        $this->payPeriod = $payPeriod;
        $this->netPay = $netPay;
    }

    public function build()
    {
        return $this->html(
            "<p>Dear {$this->employeeName},</p>
         <p>Your payroll for the period of {$this->payPeriod} has been processed.</p>
         <p>Your net pay is: <strong>" . number_format($this->netPay, 2) . " KES</strong></p>"
        )->subject('Payroll Processed');
    }
}