<?php
$plugin_name = 'cc-woocommerce-gateway-powertranz';
$target = "../".$plugin_name;

$zip = new ZipArchive();
if ($zip->open($plugin_name.".zip", ZipArchive::CREATE | ZipArchive::OVERWRITE)!==TRUE) {
    exit("cannot open <$plugin_name.zip>\n");
}


function recursiveRemoveDirectory($directory)
{
    foreach(glob("{$directory}/*") as $file)
    {
        if(is_dir($file)) {
            recursiveRemoveDirectory($file);
        } else {
            unlink($file);
        }
    }
    rmdir($directory);
}

function recursiveCopyDirectory($directory,$plugin_name)
{
    mkdir($plugin_name."/".$directory);
    foreach(glob("{$directory}/*") as $file)
    {
        if(is_dir($file) && !in_array($file, [".",".."])) {
            recursiveCopyDirectory($file, $plugin_name);
        } else {
            copy($file, $plugin_name."/".$file);
        }
    }
}

function recursiveZipDirectory(ZipArchive $zip,$directory,$plugin_name)
{
    foreach(glob("{$directory}/*") as $file)
    {
        if(is_dir($file) && !in_array($file, [".",".."])) {
            $zip->addEmptyDir(str_replace("../$plugin_name/", "", $file));
            recursiveZipDirectory($zip,$file,$plugin_name);
        } else {
            $zip->addFile(str_replace("../$plugin_name/","",str_replace("..\\$plugin_name\\", "", $file)));
        }
    }
}

if (is_dir($target))
{
    recursiveRemoveDirectory($target);
}

mkdir($target);
recursiveCopyDirectory("src",$target);
recursiveCopyDirectory("vendor",$target);
recursiveCopyDirectory("assets",$target);
recursiveZipDirectory($zip,$target, $plugin_name);

$zip->addFile($plugin_name.".php");

$zip->close();

if (is_dir($target))
{
    recursiveRemoveDirectory($target);
}