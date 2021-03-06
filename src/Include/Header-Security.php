<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

use EcclesiaCRM\dto\SystemURLs;
use EcclesiaCRM\dto\SystemConfig;

$csp = array(
    "default-src 'self'",
    "script-src 'unsafe-eval' 'self' 'nonce-".SystemURLs::getCSPNonce()."' sidecar.gitter.im browser-update.org maps.googleapis.com",
    "object-src 'none'",
    "style-src 'self' 'unsafe-inline' fonts.googleapis.com",
    "img-src 'self' www.google.com d maps.gstatic.com maps.googleapis.com data:",
    "media-src 'self'",
    "frame-src 'self' www.youtube.com",
    "font-src 'self' fonts.gstatic.com",
    "connect-src 'self'",
    "report-uri ".SystemURLs::getRootPath()."/api/system/csp-report"
);
if (SystemConfig::getBooleanValue("bHSTSEnable")) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header('X-Frame-Options: SAMEORIGIN');
header("Content-Security-Policy-Report-Only:".join(";", $csp));
