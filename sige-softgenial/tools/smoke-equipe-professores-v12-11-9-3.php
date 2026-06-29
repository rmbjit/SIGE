<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
require __DIR__ . '/smoke-equipe-professores-v12-11-9-4.php';
