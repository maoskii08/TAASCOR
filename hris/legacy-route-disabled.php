<?php
http_response_code(410);
header('Cache-Control: no-store');
header('Content-Type: text/plain; charset=utf-8');
exit('This legacy route has been retired. Use the canonical TAASCOR HRIS route.');
