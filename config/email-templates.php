<?php

return [
    'defaults' => [
        'invoice' => [
            'subject' => 'Invoice from {ACCOUNT_NAME}',
            'body' => '<p>Hi <span data-type="mergeTag" data-id="client_name"></span> ,</p><p>I hope you\'re doing well.</p><p>Please find the details of your invoice below:</p><ul><li><p><strong>Invoice Number:</strong> <span data-type="mergeTag" data-id="invoice_number"></span></p></li><li><p><strong>Project Name:</strong> <span data-type="mergeTag" data-id="project_name"></span></p></li><li><p><strong>Invoice Amount:</strong> <span data-type="mergeTag" data-id="invoice_amount"></span></p></li><li><p><strong>Issued Date:</strong> <span data-type="mergeTag" data-id="issued_date"></span></p></li><li><p><strong>Due Date:</strong> <span data-type="mergeTag" data-id="due_date"></span></p></li></ul><p>You can review the invoice and arrange payment at your convenience before the due date.</p><p><strong>Notes:</strong><br><span data-type="mergeTag" data-id="notes"></span></p><p>If you have any questions or need any clarification, feel free to reach out. I\'m happy to help.</p><p>Thank you for your business—I appreciate the opportunity to work with you.</p><p>Best regards,<br><span data-type="mergeTag" data-id="account_name"></span></p>',
        ],
    ],
    'merge_tags' => [
        'INVOICE' => [
            'client_name' => 'Client Name',
            'invoice_number' => 'Invoice Number',
            'invoice_amount' => 'Invoice Amount',
            'issued_date' => 'Issued Date',
            'due_date' => 'Due Date',
            'account_name' => 'Account Name',
            'project_name' => 'Project Name',
            'notes' => 'Invoice Notes',
        ],
    ],
];
