   <?php
   /**
    * Used only when running via PHP's built-in server (`php -S ... router.php`),
    * which is what Railway's Procfile does. The built-in server doesn't read
    * .htaccess, so this replicates the same protection: block direct access to
    * internal folders and files, let everything else (index.php, api/*.php)
    * through normally.
    *
    * On a classic Apache host (shared hosting, etc.) this file is unused —
    * .htaccess does the same job there instead.
    */

   $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

   $blockedPrefixes = ['/src/', '/config/', '/vendor/', '/db/'];
   $blockedExact = ['/.env', '/composer.json', '/composer.lock', '/.htaccess', '/Procfile', '/README.md', '/router.php'];

   foreach ($blockedPrefixes as $prefix) {
       if (str_starts_with($path, $prefix)) {
           http_response_code(403);
           header('Content-Type: application/json; charset=utf-8');
           echo json_encode(['error' => 'Forbidden']);
           return true;
       }
   }
   if (in_array($path, $blockedExact, true)) {
       http_response_code(403);
       header('Content-Type: application/json; charset=utf-8');
       echo json_encode(['error' => 'Forbidden']);
       return true;
   }

   // Not a blocked path — let the built-in server handle it normally
   // (serves index.php, api/*.php, or a static file as-is).
   return false;
