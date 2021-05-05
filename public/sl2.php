<?php

$target = "/home/pxvim6av41qx/public_html/documentos";
$link = "doc";
symlink($target, $link);
echo readlink($link);