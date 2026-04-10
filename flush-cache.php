<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache flushed.";
} else {
    echo "OPcache not available.";
}