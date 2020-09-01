<?php
define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
define("NO_AGENT_CHECK", true);
define("LANG", "s1");
set_time_limit(0);

function myEcho($text)
{
    echo $text.PHP_EOL;
}

myEcho("Начало работы скрипта\r\n");

$arParams = getopt("k::p::s::t::", ['docroot::']);

$_DOC_ROOT = realpath(dirname(__FILE__) . '/../');

/**
 * rewrite default $_DOC_ROOT if path via --docroot provided
 */

if (isset($arParams['docroot'])) {
    $_DOC_ROOT = $arParams['docroot'];

    if (empty($_DOC_ROOT) || !is_dir($_DOC_ROOT)) {
        myEcho("Директория $_DOC_ROOT не найдена");
        exit;
    }

    if (!is_writable($_DOC_ROOT)) {
        myEcho("Директория $_DOC_ROOT не доступна для записи");
        exit;
    }
}

$_SERVER['DOCUMENT_ROOT'] = $_DOC_ROOT;

$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

$prolog = $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php";
$epilog = $_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php";

if (!file_exists($prolog)) {
    myEcho("Необходимо запускать файл из корня проекта. Не найден пролог.");
    die();
}

require_once $prolog;

//Режим разработки
COption::SetOptionString("main", "update_devsrv", 'Y');
//Отключить резервное копирование
COption::SetOptionInt("main", "dump_auto_enable_auto", 0);

//установлен параметр -t
if (isset($arParams['t'])) {
    global $DB;
    $arTitle = $DB->Query("SELECT * from b_option_site WHERE MODULE_ID='bitrix24' AND NAME='site_title'")->Fetch();
    $logoB24Title = trim($arParams['t']);
    $siteTitle = $arTitle && $arTitle['VALUE'] ? $arTitle['VALUE'] : null;
    if ($logoB24Title && $siteTitle) {
        $DB->Update('b_option_site', array('VALUE' => "'$logoB24Title'"), "WHERE MODULE_ID='bitrix24' AND NAME='site_title'");
    }
}

//установлен параметр -p
if (isset($arParams['p'])) {
    COption::SetOptionString('main', 'site_stopped', 'Y');
    myEcho("Закрыта публичная часть");
}

//Смена имени сервера
if (isset($arParams['s']) && is_string($arParams['s'])) {
    COption::SetOptionString('main', 'server_name', $arParams['s']);
    myEcho("Изменено имя сревера");
}

// установлен параметр -k
// бэкап ключа
if (isset($arParams['k'])) {
    $empty_key = '<? $LICENSE_KEY = ""; ?>';
    $empty_key_len = strlen($empty_key);

    $key_file = $_SERVER['DOCUMENT_ROOT'].'/bitrix/license_key.php';
    $key_file_tmp = $key_file.'.tmp';
    $key_file_bck = preg_replace('/\.php$/', '-'.date("YmdHis").'.bck.php', $key_file);

    $written = file_put_contents($key_file_tmp, $empty_key);

    if ($written !== $empty_key_len) {
        myEcho("Не удалось записать файл с пустым ключом. Возможно закончилось место на диске или inodes.");
        exit;
    }

    // disk space can be empty here too :]
    if (copy($key_file, $key_file_bck) === false) {
        myEcho("Не удалось сделать бэкап ключа.");
        unlink($key_file_tmp);
        exit;
    }

    // but not here
    rename($key_file_tmp, $key_file);

    myEcho("Удален лицензионный ключ");
}

//Перенаправить почту в файл
$dir = '/bitrix';
if (file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface')) {
    $dir = '/local';
}
$customMailFile = $_SERVER['DOCUMENT_ROOT'] . "/{$dir}/php_interface/custom_mail.php";
copy(__DIR__ . '/custom_mail.php', $customMailFile);
myEcho("Создан файл с функцией custom_mail ({$customMailFile}). Нужно его подключить.");

//Автокеширование отключено
COption::SetOptionString('main', 'component_cache_on', 'N');
myEcho("Автокэширование отключено");
//Очистка кеша
BXClearCache(true);
\Bitrix\Main\Data\Cache::clearCache(true);
(new \Bitrix\Main\Data\ManagedCache())->cleanAll();
$stackCache = new CStackCacheManager();
$stackCache->CleanAll();
if (class_exists('\Bitrix\Main\Composite\Page')) {
    \Bitrix\Main\Composite\Page::getInstance()->deleteAll();
} else {
    $staticHtmlCache = \Bitrix\Main\Data\StaticHtmlCache::getInstance();
    $staticHtmlCache->deleteAll();
}
myEcho("Кэш очищен");

//Отключить автоматический прием писем
if (CModule::IncludeModule('mail')) {
    $obMail = new CMailbox();
    $obMails = CMailbox::GetList();
    while ($arMail = $obMails->Fetch()) {
        $obMail->update($arMail['ID'], array('ACTIVE' => 'N'));
    }
    myEcho("Почтовые ящики отключены");
}

//Поиск блока перекидывания сайта на https в .htaccess и .htaccess.restore
foreach (array('SIMPLE' => '.htaccess', 'RESTORE' => '.htaccess.restore') as $type => $htaccessPath) {
    $htaccess = file($_SERVER['DOCUMENT_ROOT'].'/'.$htaccessPath, FILE_SKIP_EMPTY_LINES);
    $isHttpsRequire = false;
    foreach ($htaccess as $rule) {
        if (preg_match('#^.+?https:\\/\\/%{(HTTP_HOST|SERVER_NAME)}%{REQUEST_URI}.+?$#si', $rule) != false) {
            $isHttpsRequire = true;
            break;
        }
    }
    if ($isHttpsRequire) {
        myEcho("в файле {$htaccessPath} найден редирект на https.");
    }
}

if (CModule::IncludeModule('security')) {
    COption::SetOptionString("main", "use_session_id_ttl", "N");
    CSecuritySession::deactivate();
}


/*
 * Обновление списка сайтов
 */
if (isset($arParams['s']) && is_string($arParams['s'])) {
    $dbSite = CSite::GetList($by = "ID", $order="ASC");
    $counter = 0;
    while ($arSite = $dbSite->Fetch()) {
        $counter++;
        if ($arSite['DIR'] === '/') {
            $arFields = array(
                "DOMAINS" => $arParams['s'],
                "SERVER_NAME" => $arParams['s']
            );
            (new CSite)->Update($arSite['ID'], $arFields);
        }
    }
    if ($counter === 1) {
        myEcho("Изменены настройки сайта для папки /.");
    } else {
        myEcho("Изменены настройки сайта для папки /. Необходимо проверить корректность настройки сайтов для других папок");
    }
}

myEcho("Площадка настроена под разработку");
//require $epilog;