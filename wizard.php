<?php
if(!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true)die();

class SetDevelopmentOptions extends CWizardStep
{
    function InitStep()
    {
        $this->SetStepID('main_fiels');
        $this->SetTitle('Настройки dev версии');
        $this->SetSubTitle('Выберите необходимые настройки для версии под разработку.');

        $this->SetNextStep("success");
        $this->SetCancelStep("cancel");

        $wizard =& $this->GetWizard();
    }

    function OnPostForm()
    {
        $wizard =& $this->GetWizard();

        if($wizard->IsCancelButtonClick())
            return;

        $success = true;

        if($wizard->GetVar('password') === 'tbdn2018')
        {
            $wizard->SetVar('correct_password', 'Y');
            //Закрываем публичную часть
            if(trim($wizard->GetVar('closePublic')) === "Y")
                Bitrix\Main\Config\Option::set('main', 'site_stopped', 'Y', false);
            //Режим разработки
            Bitrix\Main\Config\Option::set('main', 'update_devsrv', 'Y', false);
            //Отключить резервное копирование
            Bitrix\Main\Config\Option::set('main', 'dump_auto_enable_auto', 0, false);

            global $DB;
            $arTitle = $DB->Query("SELECT * from b_option_site WHERE MODULE_ID='bitrix24' AND NAME='site_title'")->Fetch();
            $logoB24Title = trim($wizard->GetVar('logoTitleBx24'));
            $siteTitle = $arTitle && $arTitle['VALUE'] ? $arTitle['VALUE'] : null;
            if($logoB24Title && $siteTitle) {
                $DB->Update('b_option_site', array('VALUE' => "'$logoB24Title'"), "WHERE MODULE_ID='bitrix24' AND NAME='site_title'");
            }

            //Смена имени сервера
            if(trim($wizard->GetVar('serverName')))
                Bitrix\Main\Config\Option::set('main', 'server_name', $wizard->GetVar('serverName'));
            else
                Bitrix\Main\Config\Option::set('main', 'server_name', $_SERVER['SERVER_NAME']);

            //Автокеширование отключено
            Bitrix\Main\Config\Option::set('main', 'component_cache_on', 'N');

            //Очистка кеша
            BXClearCache(true);
            (new \Bitrix\Main\Data\ManagedCache())->cleanAll();
            $GLOBALS["CACHE_MANAGER"]->CleanAll();
            $GLOBALS["stackCacheManager"]->CleanAll();
            $staticHtmlCache = \Bitrix\Main\Data\StaticHtmlCache::getInstance();
            $staticHtmlCache->deleteAll();

            //Перенаправить почту в файл
            $dir = '/bitrix';
            if(file_exists($_SERVER['DOCUMENT_ROOT'].'/local/php_interface'))
                $dir = '/local';

            $prefix = '';
            if(file_exists($dir.'/php_inteface/custom_mail.php'))
                $prefix = 'dev_';

            copy($_SERVER['DOCUMENT_ROOT'].'/bitrix/wizards/tobdn/devoptions/resources/custom_mail.php', $_SERVER['DOCUMENT_ROOT'].$dir.'/php_interface/'.$prefix.'custom_mail.php');
            $wizard->SetVar('CUSTOM_MAIL', $prefix.'custom_mail.php');


            //Занести адрес dev-площадки в .settings.php
            $configuration = Bitrix\Main\Config\Configuration::getInstance();
            $prevDev = $configuration->get('dev_sites') ? $configuration->get('dev_sites') : array();
            if(!in_array($_SERVER['SERVER_NAME'], $prevDev))
                $prevDev[] = $_SERVER['SERVER_NAME'];
            $configuration->add('dev_sites',  $prevDev);
            $configuration->saveConfiguration();

            //Отключить автоматический прием писем
            if(CModule::IncludeModule('mail'))
            {
                $obMail = new CMailbox();
                $obMails = CMailbox::GetList();
                while($arMail = $obMails->Fetch())
                {
                    $obMail->update($arMail['ID'], array('ACTIVE' => 'N'));
                }
            }

            //Удалить лицензионный ключ
            $deleteKey = $wizard->GetVar('deleteKey') === 'Y' ? 'Y' : 'N';
            if($deleteKey === 'Y')
            {
                copy($_SERVER['DOCUMENT_ROOT'].'/bitrix/license_key.php', $_SERVER['DOCUMENT_ROOT'].'/bitrix/license_key.php.dev');
                $f = fopen($_SERVER['DOCUMENT_ROOT'].'/bitrix/license_key.php', 'w+');
                fwrite($f, '<? $LICENSE_KEY = ""; ?>');
                fclose($f);
            }

            //Проверка агента
            $agentWork = false;
            if(defined('BX_CRONTAB_SUPPORT'))
                $agentWork = true;

            $wizard->SetVar('CRONTAB', $agentWork);

            //Поиск блока перекидывания сайта на https в .htaccess и .htaccess.restore
            foreach (array('SIMPLE' => '.htaccess', 'RESTORE' => '.htaccess.restore') as $type => $htaccessPath)
            {
                $htaccess = file($_SERVER['DOCUMENT_ROOT'].'/'.$htaccessPath, FILE_SKIP_EMPTY_LINES);
                $isHttpsRequire = false;
                foreach ($htaccess as $rule)
                {
                    if(preg_match('#^.+?https:\\/\\/%{(HTTP_HOST|SERVER_NAME)}%{REQUEST_URI}.+?$#si', $rule) != false)
                    {
                        $isHttpsRequire = true;
                        break;
                    }
                }
                $wizard->SetVar('HTTPS_REQUIRED_'.$type, $isHttpsRequire);
            }

            if(CModule::IncludeModule('security'))
            {
                COption::SetOptionString("main", "use_session_id_ttl", "N");
                CSecuritySession::deactivate();
            }

            /*
             * Обновление списка сайтов
             */
            $arSite = CSite::GetList($by = "ID", $oreder="ASC", array('DIR' => "/"))->Fetch();
            $arFields = array(
                "DOMAINS" => $_SERVER['SERVER_NAME'],
                "SERVER_NAME" => $_SERVER['SERVER_NAME']
            );
            (new CSite)->Update($arSite['ID'], $arFields);
        }
        else
            $wizard->SetVar('correct_password', 'N');

    }

    function ShowStep()
    {
        global $DB;
        $arResult = $DB->Query("SELECT * from b_option_site WHERE MODULE_ID='bitrix24' AND NAME='site_title'")->Fetch();

        $this->content .= '<link rel="stylesheet" href="/bitrix/wizards/tobdn/devoptions/style.css">';
        $this->content .= '<table class="wizard-data-table settings_table">';

        $this->content .= '<tr><th align="right">Введите пароль:</th><td align="center">';
        $this->content .= $this->ShowInputField("text", "password", Array("size" => 25));
        $this->content .= '</td></tr>';
        $this->content .= '<tr><th align="right">Закрыть публичную часть:</th><td align="center">'.$this->ShowRadioField("closePublic", "Y").'Да   '.$this->ShowRadioField("closePublic", "N").'Нет</td></tr>';
        $this->content .= '<tr><th align="right">Включить режим разработки:</th><td align="center">Да</td></tr>';
        $this->content .= '<tr><th align="right">URL-сайта <br>(оставьте поле пустым для автоопределения):</th><td align="center">';
        $this->content .= $this->ShowInputField("text", "serverName", Array("size" => 25));
        $this->content .= '</td></tr>';

        if($arResult && trim($arResult['VALUE'])) {
            $this->content .= '<tr><th align="right">Название в логотипе для (bx24):</th><td align="center">';
            $this->content .= $this->ShowInputField("text", "logoTitleBx24", Array("size" => 25));
            $this->content .= '</td></tr>';
        }
        $this->content .= '<tr><th align="right">Отключить регулярное резервное копирование:</th><td align="center">Да</td></tr>';
        $this->content .= '<tr><th align="right">Отключить автокеширование:</th><td align="center">Да</td></tr>';
        $this->content .= '<tr><th align="right">Очистить кеш:</th><td align="center">Да</td></tr>';
        $this->content .= '<tr><th align="right">В .settings.php внести данную<br> площдку как тестовую:</th><td align="center">Да</td></tr>';
        $this->content .= '<tr><th align="right">Созадть файл с функцией custom_mail:</th><td align="center">Да</td></tr>';
        $this->content .= '<tr><th align="right">Деактивировать почтовые ящики:</th><td align="center">Да</td></tr>';
        $this->content .= '<tr><th align="right">Удалить лицензионный ключ:</th><td align="center">'.$this->ShowCheckBoxField("deleteKey", "Y").'</td></tr>';
        $this->content .= '<tr><th align="right">Проверить режим работы агентов:</th><td align="center">Да</td></tr>';
        $this->content .= '<tr><th align="right">Проверка редиректа на https:</th><td align="center">Да</td></tr>';
        $this->content .= '</table>';
    }
}

class SuccessStep extends CWizardStep
{
    function InitStep()
    {
        $wizard =& $this->GetWizard();
        $title = $wizard->GetVar('correct_password') === 'Y' ? "Работа мастера успешно завершена" : "Ошибка!";
        $this->SetStepID("success");
        $this->SetTitle($title);
        //Навигация
        $this->SetCancelStep("success");
        $this->SetCancelCaption("Готово");
    }

    function ShowStep()
    {
        $wizard =& $this->GetWizard();
        $this->content .= "<link rel='stylesheet' href='/bitrix/wizards/tobdn/devoptions/style.css'>";
        if($wizard->GetVar('correct_password') === 'Y')
        {
            $dir = file_exists($_SERVER['DOCUMENT_ROOT'].'/local') ? 'local' : 'bitrix';
            $this->content .= "<div class='success_status'>Площадка настроена для разработки!</div>";
            $this->content .= "<div class='success_notification'><span class='notification_title'>Перенаправдение почты:</span> В папке {$dir} создана файл {$wizard->GetVar('CUSTOM_MAIL')} для записи исходящей почты в файл. Не забудьте его подключить!</div>";
            $this->content .= "<div class='success_notification'><span class='notification_title'>Сайты:</span> Не забудьте настроить сайты в <a href='/bitrix/admin/site_admin.php'>списке сайтов</a></div>";
            if($wizard->GetVar('CRONTAB'))
                $this->content .= "<div class='success_notification'><span class='notification_title'>Работа агентов:</span> определена константа <span class='notification_detail'>BX_CRONTAB_SUPPORT</span></div>";
            foreach (array('SIMPLE', 'RESTORE') as $htaccessType)
            {
                $curFile = $htaccessType === 'SIMPLE' ? '.htaccess' : '.htaccess.restore';
                if($wizard->GetVar('HTTPS_REQUIRED_'.$htaccessType))
                    $this->content .= "<div class='success_notification'><span class='notification_title'>HTTPS:</span> В файле <span class='notification_detail'>{$curFile}</span> найден блок редиректа на https. Необходимо его удалить.</div>";
            }
        }
        else
            $this->content .= "<div><span class='notification_detail'>Ошибка!</span> Введен неправильный пароль!</div>";
    }
}

class CancelStep extends CWizardStep
{
    function InitStep()
    {
        $this->SetTitle("Мастер прерван");
        $this->SetStepID("cancel");
        $this->SetCancelStep("cancel");
        $this->SetCancelCaption("Закрыть");
    }

    function ShowStep()
    {
        $this->content .= "Мастер настройки dev-площадки прерван!";
    }
}