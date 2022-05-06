<?php
/**
 * Local Configuration Override
 *
 * This configuration override file is for overriding environment-specific and
 * security-sensitive configuration information. Copy this file without the
 * .dist extension at the end and populate values as needed.
 *
 * @NOTE: This file is ignored from Git by default with the .gitignore included
 * in ZendSkeletonApplication. This is a good practice, as it prevents sensitive
 * credentials from accidentally being committed into version control.
 */

return [
    /**
     * Email adres where the results of the checker is send to
     */
    'checker' => [
        'report_mail' => getenv('CHECKER_REPORT_MAIL'),
        'membership_api' => [
            'endpoint' => getenv('CHECKER_MEMBERSHIP_API_ENDPOINT'),
            'key' => getenv('CHECKER_MEMBERSHIP_API_KEY'),
            'max_total_requests' => getenv('CHECKER_MEMBERSHIP_API_MAX_TOTAL_REQUESTS'),
            'max_manual_requests' => getenv('CHECKER_MEMBERSHIP_API_MAX_MANUAL_REQUESTS')
        ]
    ],


    /**
     * Email configuration.
     */
    'email' => [
        'transport' => getenv('EMAIL_TRANSPORT'),
        'options' => json_decode(getenv('EMAIL_OPTIONS'), true),
        'from' => getenv('EMAIL_FROM'),
        'to' => [
            'report_error' => getenv('EMAIL_TO_REPORT_ERROR'),
            'subscription' => getenv('EMAIL_TO_SUBSCRIPTION')
        ]
    ]
];
