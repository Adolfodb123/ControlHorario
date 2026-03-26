<?php
var_dump(function_exists('apcu_store')); // debería dar true
apcu_store('prueba', 'ok', 60);
var_dump(apcu_fetch('prueba', $success), $success);
?>