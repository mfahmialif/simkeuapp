<?php
if (! function_exists('public_path')) {
    function public_path($path = '')
    {
        // Change this to your custom path
        $customPath = base_path('../public_html');

        return $customPath . ($path ? DIRECTORY_SEPARATOR . $path : '');
    }
}
