<?php

namespace App\Helpers;

class VietQRHelper
{
    
    public static function generateLink(
        ?string $bankBin = null,
        ?string $accountNo = null,
        ?string $accountName = null,
        float $amount = 0,
        ?string $description = null,
        ?string $template = null
    ): string {
        $baseUrl = "https://api.vietqr.io/image/";
        
        $bankBin = $bankBin ?: config('services.vietqr.bank_bin', '970423');
        $accountNo = $accountNo ?: config('services.vietqr.account_number', '99928876789');
        $accountName = $accountName ?: config('services.vietqr.account_name', 'LE VU VINH KHANG');
        $template = $template ?: config('services.vietqr.template', 'VNnocPH');
        
        $params = [
            'accountName' => $accountName,
            'amount' => (int) $amount,
        ];
        
        if (!empty($description)) {
            $params['addInfo'] = $description;
        }
        
        $path = sprintf(
            '%s-%s-%s.jpg',
            urlencode($bankBin),
            urlencode($accountNo),
            urlencode($template)
        );
        
        return $baseUrl . $path . '?' . http_build_query($params);
    }
}
