<?php
function custom_mail($to, $subject, $message, $additional_headers=false, $additional_parametres=false)
{
    $f = fopen($_SERVER['DOCUMENT_ROOT'].'/mail_messages.php', 'a+');
    $strStart = "=================================================\r\n".date('d.m.Y h:i:s')."\r\n=================================================\r\n";
    fwrite($f, $strStart);
    fwrite($f, "TO: $to \r\n");
    fwrite($f, "Subject: $subject \r\n");
    fwrite($f, "Message: $message \r\n");
    if($additional_headers || $additional_parametres)
        fwrite($f, "Additional: $additional_headers \r\n $additional_parametres\r\n");

    fclose($f);
}


?>