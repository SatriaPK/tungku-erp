<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Invoice Configuration
    |--------------------------------------------------------------------------
    */

    /**
     * Default due date in days from issue date.
     */
    'default_due_days' => env('INVOICE_DEFAULT_DUE_DAYS', 7),

    /**
     * Tax rate percentage (0 = no tax).
     */
    'tax_rate' => env('INVOICE_TAX_RATE', 0),

    /**
     * Company information for invoices.
     */
    'company' => [
        'name' => env('COMPANY_NAME', 'ERP Tungku'),
        'address' => env('COMPANY_ADDRESS', 'Jl. Example No. 123'),
        'city' => env('COMPANY_CITY', 'Jakarta'),
        'postal_code' => env('COMPANY_POSTAL_CODE', '12345'),
        'phone' => env('COMPANY_PHONE', '+62 21 1234567'),
        'email' => env('COMPANY_EMAIL', 'info@erptungku.com'),
        'website' => env('COMPANY_WEBSITE', 'https://erptungku.com'),
        'logo_path' => env('COMPANY_LOGO_PATH', 'images/logo.png'),
    ],

    /**
     * Bank account information for payment instructions.
     */
    'bank_accounts' => [
        [
            'bank_name' => 'Bank Mandiri',
            'account_number' => '1234567890',
            'account_holder' => 'ERP Tungku Studio',
        ],
        [
            'bank_name' => 'Bank BCA',
            'account_number' => '0987654321',
            'account_holder' => 'ERP Tungku Studio',
        ],
    ],
];
