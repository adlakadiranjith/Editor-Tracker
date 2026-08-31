<?php
// Second layer of defense in case .htaccess is ignored (e.g. nginx, or
// AllowOverride disabled). Nothing in this directory should ever be served.
http_response_code(403);
exit;
