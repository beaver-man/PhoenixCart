<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License

  browser language detection logic Copyright phpMyAdmin (select_lang.lib.php3 v1.24 04/19/2002)
                                   Copyright Stephane Garin <sgarin@sgarin.com> (detect_language.php v0.1 04/02/2002)
*/

  class language {
    var $languages, $catalog_languages, $browser_languages, $language;

    function __construct($lng = '') {
      $this->languages = ['af' => 'af|afrikaans',
                               'ar' => 'ar([-_][[:alpha:]]{2})?|arabic',
                               'be' => 'be|belarusian',
                               'bg' => 'bg|bulgarian',
                               'br' => 'pt[-_]br|brazilian portuguese',
                               'ca' => 'ca|catalan',
                               'cs' => 'cs|czech',
                               'da' => 'da|danish',
                               'de' => 'de([-_][[:alpha:]]{2})?|german',
                               'el' => 'el|greek',
                               'en' => 'en([-_][[:alpha:]]{2})?|english',
                               'es' => 'es([-_][[:alpha:]]{2})?|spanish',
                               'et' => 'et|estonian',
                               'eu' => 'eu|basque',
                               'fa' => 'fa|farsi',
                               'fi' => 'fi|finnish',
                               'fo' => 'fo|faeroese',
                               'fr' => 'fr([-_][[:alpha:]]{2})?|french',
                               'ga' => 'ga|irish',
                               'gl' => 'gl|galician',
                               'he' => 'he|hebrew',
                               'hi' => 'hi|hindi',
                               'hr' => 'hr|croatian',
                               'hu' => 'hu|hungarian',
                               'id' => 'id|indonesian',
                               'it' => 'it|italian',
                               'ja' => 'ja|japanese',
                               'ko' => 'ko|korean',
                               'ka' => 'ka|georgian',
                               'lt' => 'lt|lithuanian',
                               'lv' => 'lv|latvian',
                               'mk' => 'mk|macedonian',
                               'mt' => 'mt|maltese',
                               'ms' => 'ms|malaysian',
                               'nl' => 'nl([-_][[:alpha:]]{2})?|dutch',
                               'no' => 'no|norwegian',
                               'pl' => 'pl|polish',
                               'pt' => 'pt([-_][[:alpha:]]{2})?|portuguese',
                               'ro' => 'ro|romanian',
                               'ru' => 'ru|russian',
                               'sk' => 'sk|slovak',
                               'sq' => 'sq|albanian',
                               'sr' => 'sr|serbian',
                               'sv' => 'sv|swedish',
                               'sz' => 'sz|sami',
                               'sx' => 'sx|sutu',
                               'th' => 'th|thai',
                               'ts' => 'ts|tsonga',
                               'tr' => 'tr|turkish',
                               'tn' => 'tn|tswana',
                               'uk' => 'uk|ukrainian',
                               'ur' => 'ur|urdu',
                               'vi' => 'vi|vietnamese',
                               'tw' => 'zh[-_]tw|chinese traditional',
                               'zh' => 'zh|chinese simplified',
                               'ji' => 'ji|yiddish',
                               'zu' => 'zu|zulu'];

      $this->catalog_languages = [];
      $languages_query = $GLOBALS['db']->query("select languages_id, name, code, image, directory from languages order by sort_order");
      while ($languages = $languages_query->fetch_assoc()) {
        $this->catalog_languages[$languages['code']] = [
          'id' => $languages['languages_id'],
          'name' => $languages['name'],
          'image' => $languages['image'],
          'directory' => $languages['directory'],
        ];
      }

      $this->browser_languages = '';
      $this->language = '';

      $this->set_language($lng);
    }

    function set_language($language) {
      if ( (!Text::is_empty($language)) && (isset($this->catalog_languages[$language])) ) {
        $this->language = $this->catalog_languages[$language];
      } else {
        $this->language = $this->catalog_languages[DEFAULT_LANGUAGE];
      }
    }

    function get_browser_language() {
      $this->browser_languages = explode(',', getenv('HTTP_ACCEPT_LANGUAGE'));

      for ($i=0, $n=count($this->browser_languages); $i<$n; $i++) {
        foreach($this->languages as $key => $value) {
          if (preg_match('/^(' . $value . ')(;q=[0-9]\\.[0-9])?$/i', $this->browser_languages[$i]) && isset($this->catalog_languages[$key])) {
            $this->language = $this->catalog_languages[$key];
            break 2;
          }
        }
      }
    }

  }
