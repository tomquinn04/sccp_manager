<?php

//namespace FreePBX\modules;
//  License for all code of this FreePBX module can be found in the license file inside the module directory
//  Copyright 2015 Sangoma Technologies.
// https://github.com/chan-sccp/chan-sccp/wiki/Setup-FreePBX
// http://chan-sccp-b.sourceforge.net/doc/setup_sccp.xhtml
// https://github.com/chan-sccp/chan-sccp/wiki/Conferencing
// https://github.com/chan-sccp/chan-sccp/wiki/Frequently-Asked-Questions
// http://chan-sccp-b.sourceforge.net/doc/_howto.xhtml#nf_adhoc_plar
// https://www.cisco.com/c/en/us/td/docs/voice_ip_comm/cuipph/all_models/xsi/9-1-1/CUIP_BK_P82B3B16_00_phones-services-application-development-notes/CUIP_BK_P82B3B16_00_phones-services-application-development-notes_chapter_011.html
// https://www.cisco.com/c/en/us/td/docs/voice_ip_comm/cuipph/7960g_7940g/sip/4_4/english/administration/guide/ver4_4/sipins44.html
// http://usecallmanager.nz/
/* !TODO!:
 *  + Cisco Format Mac
 *  + Model Information
 *  + Device Right Menu
 *  - Dial Templates are not really needed for skinny, skinny get's direct feed back from asterisk per digit -->
 *  - If your dialplan is finite (completely fixed length (depends on your country dialplan) dialplan, then dial templates are not required) -->
 *  - As far as i know FreePBX does also attempt to build a finite dialplan -->
 *  - Having to maintain both an asterisk dialplan and these skinny dial templates is annoying -->
 *  + Dial Templates + Configuration
 *  + Dial Templates in Global Configuration ( Enabled / Disabled ; default template )
 *  ? Dial Templates - Howto IT Include in XML.Config ???????
 *  + Dial Templates - SIP Device
 *  - Dial Templates in device Configuration ( Enabled / inheret / Disabled ; template )
 *  - WiFi Config (Bulk Deployment Utility for Cisco 7921, 7925, 7926)?????
 *  + Change internal use Field to _Field (new feature in chan_sccp (added for Sccp_manager))
 *  + Delete phone XML
 *  + Change Installer  ?? (test )
 *  + Installer  Realtime config update
 *  + Installer  Adaptive DB reconfig.
 *  + Add system info page
 *  + Change Cisco Language data
 *  + Make DB Acces from separate class
 *  + Make System Acces from separate class
 *  + Make Var elements from separate class
 *  + To make creating XML files in a separate class
 *  + Add Switch to select XML schema (display)
 *  + SRST Config
 *  + secondary_dialtone_digits = ""     line config
 *  + secondary_dialtone_tone = 0x22     line config
 *  - deviceSecurityMode http://usecallmanager.nz//itl-file-tlv.html
 *  - transportLayerProtocol http://usecallmanager.nz//itl-file-tlv.html
 *  - Check Time zone ....
 *  - Failover config
 *  + Auto Addons!
 *  + DND Mode
 *  - support kv-store ?????
 *  + Shared Line
 *  - bug Soft key set (empty keysets )
 *  - bug Fix ...(K no w bug? no fix)
 *  - restore default Value on page
 *  - restore default Value on sccp.class
 *  -  'Device SEP ID.[XXXXXXXXXXXX]=MAC'
 *  +  ATA's start with       ATAXXXXXXXXXXXX.
 *  + Create ATADefault.cnf.xml
 *  - Create Second line Use MAC AABBCCDDEEFF rotation MAC BBCCDDEEFF01 (ATA 187 )
 *  +  Add SEP, ATA, VG prefix.
 *  +  Add Cisco SIP device Tftp config.
 *  -  VG248 ports start with VGXXXXXXXXXXXX0.
 *  * I think this file should be split in 3 parts (as in Model-View-Controller(MVC))
 *    * XML/Database Parts -> Model directory
 *    * Processing parts -> Controller directory
 *    * Ajax Handler Parts -> Controller directory
 *    * Result parts -> View directory
 *  + Support TFTP rewrite :
 *     + dir "settings"
 *     + dir "templates"
 *     + dir "firmware"
 *     + dir "locales"
 *  + Create Simple User Interface
 *       + sccpsimple.xml
 *  + Add error information on the server information page (critical display error - the system can not work correctly)
 *  - Add Warning Information on Server Info Page
 *  - ADD Reload Line
 *  - Add Call Map (show Current call Information)
 * ---TODO ---
 * <vendorConfig>
 *  <autoSelectLineEnable>0</autoSelectLineEnable>
 * <autoCallSelect>0</autoCallSelect>
 * </vendorConfig>
 */

namespace FreePBX\modules;

class Sccp_manager extends \FreePBX_Helpers implements \BMO {
    /* Field Values for type  seq */
    private $SCCP_LANG_DICTIONARY = 'be-sccp.jar'; // CISCO LANG file search in /tftp-path
    private $pagedata = null;
    private $sccp_driver_ver = '11.4';             // Ver fore SCCP.CLASS.PHP
    public $sccp_manager_ver = '14.1.0.0';
    public $sccp_branch = 'm';                       // Ver fore SCCP.CLASS.PHP
    private $tftpLang = array();

    private $hint_context = array('default' => '@ext-local'); /// !!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!! Get it from Config !!!
    private $val_null = 'NONE'; /// REPLACE to null Field
    public $sccp_model_list = array();
    public $sccp_metainfo = array();
    private $cnf_wr = null;
    public $sccppath = array();
    public $sccpvalues = array();
    public $sccp_conf_init = array();
    public $xml_data;
    public $class_error; //error construct
    public $info_warning;

    // Move all non sccp_manager specific functions to traits
    use \FreePBX\modules\Sccp_Manager\sccpManTraits\helperFunctions;
    use \FreePBX\modules\Sccp_Manager\sccpManTraits\ajaxHelper;   // TODO should migrate this to child class
    use \FreePBX\modules\Sccp_Manager\sccpManTraits\bmoFunctions;

    public function __construct($freepbx = null) {
        if ($freepbx == null) {
            throw new Exception("Not given a FreePBX Object");
        }
        $this->class_error = array();
        $this->FreePBX = $freepbx;
        $this->db = $freepbx->Database;
        $this->cnf_wr = \FreePBX::WriteConfig();
        $this->cnf_read = \FreePBX::LoadConfig();
        $driverNamespace = "\\FreePBX\\Modules\\Sccp_manager";
        if (class_exists($driverNamespace, false)) {
            foreach (glob(__DIR__ . "/sccpManClasses/*.class.php") as $driver) {
                if (preg_match("/\/([a-z1-9]*)\.class\.php$/i", $driver, $matches)) {
                    $name = $matches[1];
                    $class = $driverNamespace . "\\" . $name;
                    if (!class_exists($class, false)) {
                        include($driver);
                    }
                    if (class_exists($class, false)) {
                        $this->$name = new $class($this);
                    } else {
                        throw new \Exception("Invalid Class inside in the include folder" . print_r($freepbx));
                    }
                }
            }
        } else {
            return;
        }

        $this->sccpvalues = $this->dbinterface->get_db_SccpSetting(); // Overwrite Exist
        $this->initializeSccpPath();
        $this->initVarfromDefs();
        $this->initTftpLang();

        if (!empty($this->sccpvalues['SccpDBmodel'])) {
            if ($this->sccpvalues['sccp_compatible']['data'] > $this->sccpvalues['SccpDBmodel']['data']) {
                $this->sccpvalues['sccp_compatible']['data'] = $this->sccpvalues['SccpDBmodel']['data'];
            }
        }
        // Load Advanced Form Constructor Data
        if (empty($this->sccpvalues['displayconfig'])) {
            $xml_vars = __DIR__ . '/conf/sccpgeneral.xml.v' . $this->sccpvalues['sccp_compatible']['data'];
        } else {
            $xml_vars = __DIR__ . '/conf/' . $this->sccpvalues['displayconfig']['data'] . '.xml.v' . $this->sccpvalues['sccp_compatible']['data'];
        }
        if (!file_exists($xml_vars)) {
            $xml_vars = __DIR__ . '/conf/sccpgeneral.xml';
        }
        if (file_exists($xml_vars)) {
            $this->xml_data = simplexml_load_file($xml_vars);
            $this->initVarfromXml(); // Overwrite Exist
        }

        //if (get_class($freepbx) === 'FreePBX') {
            // only save settings when building a new FreePBX object
            $this->saveSccpSettings();
        //}
    }

    /*
     *   Generate Input elements in Html Code from sccpgeneral.xml
     */

    public function showGroup($grup_name, $heder_show, $form_prefix = 'sccp', $form_values = null) {
        $htmlret = "";
        if (empty($form_values)) {
            $form_values = $this->sccpvalues;
        }
        if ((array) $this->xml_data) {
            foreach ($this->xml_data->xpath('//page_group[@name="' . $grup_name . '"]') as $item) {
                $htmlret .= load_view(__DIR__ . '/views/formShow.php', array(
                    'itm' => $item, 'h_show' => $heder_show,
                    'form_prefix' => $form_prefix, 'fvalues' => $form_values,
                    'tftp_lang' => $this->tftpLang, 'metainfo' => $this->sccp_metainfo));
            }
        } else {
            $htmlret .= load_view(__DIR__ . '/views/formShowError.php');
        }
        return $htmlret;
    }

    /*
     *    Load config vars from base array
     */

    public function initVarfromDefs() {
        foreach ($this->extconfigs->getextConfig('sccpDefaults') as $key => $value) {
            if (empty($this->sccpvalues[$key])) {
                $this->sccpvalues[$key] = array('keyword' => $key, 'data' => $value, 'type' => '0', 'seq' => '0');
            }
        }
        // Check timezone has not been changed in FreePBX and update if has
        if ($this->sccpvalues['ntp_timezone'] != \date_default_timezone_get()) {
            $this->sccpvalues['ntp_timezone'] = array('keyword' => 'ntp_timezone', 'seq'=>95, 'type' => 2, 'data' => \date_default_timezone_get());
        }
    }

    /*
     *    Load config vars from xml
     */

    public function initVarfromXml() {
        if ((array) $this->xml_data) {
            foreach ($this->xml_data->xpath('//page_group') as $item) {
                foreach ($item->children() as $child) {
                    $seq = 0;
                    if (!empty($child['seq'])) {
                        $seq = (string) $child['seq'];
                    }
                    if ($seq < 99) {
                        if ($child['type'] == 'IE') {
                            foreach ($child->xpath('input') as $value) {
                                $tp = 0;
                                if (empty($value->value)) {
                                    $datav = (string) $value->default;
                                } else {
                                    $datav = (string) $value->value;
                                }
                                if (strtolower($value->type) == 'number') {
                                    $tp = 1;
                                }
                                if (empty($this->sccpvalues[(string) $value->name])) {
                                    $this->sccpvalues[(string) $value->name] = array('keyword' => (string) $value->name, 'data' => $datav, 'type' => $tp, 'seq' => $seq);
                                }
                            }
                        }
                        if ($child['type'] == 'IS' || $child['type'] == 'IED') {
                            if (empty($child->value)) {
                                $datav = (string) $child->default;
                            } else {
                                $datav = (string) $child->value;
                            }
                            if (empty($this->sccpvalues[(string) $child->name])) {
                                $this->sccpvalues[(string) $child->name] = array('keyword' => (string) $child->name, 'data' => $datav, 'type' => '2', 'seq' => $seq);
                            }
                        }
                        if (in_array($child['type'], array('SLD', 'SLS', 'SLT', 'SL', 'SLM', 'SLZ', 'SLTZN', 'SLA'))) {
                            if (empty($child->value)) {
                                $datav = (string) $child->default;
                            } else {
                                $datav = (string) $child->value;
                            }
                            if (empty($this->sccpvalues[(string) $child->name])) {
                                $this->sccpvalues[(string) $child->name] = array('keyword' => (string) $child->name, 'data' => $datav, 'type' => '2', 'seq' => $seq);
                            }
                        }
                    }
                }
            }
        }
    }

    /*
     *  Show form information - General
     */

    public function settingsShowPage() {
        $request = $_REQUEST;
        $action = !empty($request['action']) ? $request['action'] : '';

        $this->pagedata = array(
            "general" => array(
                "name" => _("General SCCP Settings"),
                "page" => 'views/server.setting.php'
              ),
            "sccpdevice" => array(
                "name" => _("SCCP Device"),
                "page" => 'views/server.device.php'
              ),
            "sccpurl" => array(
                "name" => _("SCCP Device URL"),
                "page" => 'views/server.url.php'
              ),
            "sccpntp" => array(
                "name" => _("SCCP Time"),
                "page" => 'views/server.datetime.php'
              ),
            "sccpcodec" => array(
                "name" => _("SCCP Codec"),
                "page" => 'views/server.codec.php'
              ),
            "sccpadv" => array(
                "name" => _("Advanced SCCP Settings"),
                "page" => 'views/server.advanced.php'
              ),
            "sccpinfo" => array(
                "name" => _("SCCP info"),
                "page" => 'views/server.info.php'
              )
            );
        // If view is set to simple, remove the ntp, codec and advanced tabs
        if (isset($this->sccpvalues['displayconfig']['data']) && ($this->sccpvalues['displayconfig']['data'] == 'sccpsimple')) {
            unset($this->pagedata['sccpntp'], $this->pagedata['sccpcodec'], $this->pagedata['sccpadv'] );
        }
        $this->processPageData();
        return $this->pagedata;
    }

    public function infoServerShowPage() {
        $request = $_REQUEST;
        $action = !empty($request['action']) ? $request['action'] : '';
        $this->pagedata = array(
            "general" => array(
                "name" => _("General SCCP Settings"),
                "page" => 'views/server.info.php'
            ),
        );
        $this->processPageData();
        return $this->pagedata;
    }

    public function advServerShowPage() {
        $request = $_REQUEST;
        $action = !empty($request['action']) ? $request['action'] : '';
        $inputform = !empty($request['tech_hardware']) ? $request['tech_hardware'] : '';
        switch ($inputform) {
            case 'dialplan':
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("SCCP Dial Plan information"),
                        "page" => 'views/form.dptemplate.php'
                    )
                );
                break;
            default:
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("SCCP Model information"),
                        "page" => 'views/advserver.model.php'
                    ),
                    "sccpkeyset" => array(
                        "name" => _("SCCP Device Keyset"),
                        "page" => 'views/advserver.keyset.php'
                    )
                );
                if ($this->sccpvalues['siptftp']['data'] == 'on') {
                    $this->pagedata["sccpdialplan"] = array(
                        "name" => _("SIP Dial Plan information"),
                        "page" => 'views/advserver.dialtemplate.php'
                    );
                }
                break;
        }

        $this->processPageData();
        return $this->pagedata;
    }

    public function phoneShowPage() {
        $request = $_REQUEST;
        $action = !empty($request['action']) ? $request['action'] : '';
        $inputform = !empty($request['tech_hardware']) ? $request['tech_hardware'] : '';
        switch ($inputform) {
            case "cisco":
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("Device configuration"),
                        "page" => 'views/form.adddevice.php'
                    ),
                    "buttons" => array(
                        "name" => _("Device Buttons"),
                        "page" => 'views/form.buttons.php'
                    ),
                    "advanced" => array(
                        "name" => _("Device SCCP Advanced"),
                        "page" => 'views/form.devadvanced.php'
                    )
                );
                break;
            case "cisco-sip":
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("Sip device configuration"),
                        "page" => 'views/form.addsdevice.php'
                    ),
                    "buttons" => array(
                        "name" => _("Sip device Buttons"),
                        "page" => 'views/form.sbuttons.php'
                    )
                );
                break;

            case "r_user":
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("Roaming User configuration"),
                        "page" => 'views/form.addruser.php'
                    ),
                    "buttons" => array(
                        "name" => _("Device Buttons"),
                        "page" => 'views/form.buttons.php'
                    ),
                );
                break;

            default:
                $this->pagedata = array(
                    "general" => array(
                        "name" => _("SCCP Extension"),
                        "page" => 'views/hardware.extension.php'
                    ),
                    "sccpdevice" => array(
                        "name" => _("SCCP Phone"),
                        "page" => 'views/hardware.phone.php'
                    )
                );
                if ($this->sccpvalues['siptftp']['data'] == 'on') {
                    $this->pagedata["sipdevice"] = array(
                        "name" => _("SIP CISCO Phone"),
                        "page" => 'views/hardware.sphone.php'
                    );
                }
                break;
        }
        $this->processPageData();
        return $this->pagedata;
    }

    public function processPageData() {
        foreach ($this->pagedata as &$page) {
            ob_start();
            include($page['page']);
            $page['content'] = ob_get_contents();
            ob_end_clean();
        }
    }

    /*
     *
     * *  Save Hardware Device Information to Db + ???? Create / update XML Profile
     *
     */

    function getPhoneButtons($get_settings, $ref_id = '', $ref_type = 'sccpdevice') {
        // get Model Buttons info
        $res = array();
        $def_feature = array('parkinglot' => array('name' => 'P.slot', 'value' => 'default'),
            'devstate' => array('name' => 'Coffee', 'value' => 'coffee'),
            'monitor' => array('name' => 'Record Calls', 'value' => '')
        );

        // $lines_list = $this->dbinterface->HWextension_db_SccpTableData('SccpExtension');
        $max_btn = ((!empty($get_settings['buttonscount']) ? $get_settings['buttonscount'] : 100));
        $last_btn = $max_btn;
        for ($it = $max_btn; $it >= 0; $it--) {
            if (!empty($get_settings['button' . $it . '_type'])) {
                $last_btn = $it;
                $btn_t = $get_settings['button' . $it . '_type'];
                if ($btn_t != 'empty') {
                    break;
                }
            }
        }

        for ($it = 0; $it <= $last_btn; $it++) {
            if (!empty($get_settings['button' . $it . '_type'])) {
                $btn_t = $get_settings['button' . $it . '_type'];

                $btn_n = '';
                $btn_opt = '';
                if ($it == 0) {
                    $btn_opt = 'default';
                }
                switch ($btn_t) {
                    case 'feature':
                        $btn_f = $get_settings['button' . $it . '_feature'];
                        // $btn_opt = (empty($get_settings['button' . $it . '_fvalue'])) ? '' : $get_settings['button' . $it . '_fvalue'];
                        $btn_n = (empty($get_settings['button' . $it . '_flabel'])) ? $def_feature[$btn_f]['name'] : $get_settings['button' . $it . '_flabel'];
                        $btn_opt = $btn_f;
                        if (!empty($def_feature[$btn_f]['value'])) {
                            if (empty($get_settings['button' . $it . '_fvalue'])) {
                                $btn_opt .= ',' . $def_feature[$btn_f]['value'];
                            } else {
                                $btn_opt .= ',' . $get_settings['button' . $it . '_fvalue'];
                            }
                            if ($btn_f == 'parkinglot') {
                                if (!empty($get_settings['button' . $it . '_retrieve'])) {
                                    $btn_opt .= ',RetrieveSingle';
                                }
                            }
                        }

                        break;
                    case 'monitor':
                        $btn_t = 'speeddial';
                        $btn_opt = (string) $get_settings['button' . $it . '_line'];
                        $db_res = $this->dbinterface->HWextension_db_SccpTableData('SccpExtension', array('name' => $btn_opt));
                        $btn_n = $db_res[0]['label'];
                        $btn_opt .= ',' . $btn_opt . $this->hint_context['default'];
                        break;
                    case 'speeddial':
                        if (!empty($get_settings['button' . $it . '_input'])) {
                            $btn_n = $get_settings['button' . $it . '_input'];
                        }
                        if (!empty($get_settings['button' . $it . '_phone'])) {
                            $btn_opt = $get_settings['button' . $it . '_phone'];
                            if (empty($btn_n)) {
                                $btn_n = $btn_opt;
                            }
                        }

                        if (!empty($get_settings['button' . $it . '_hint'])) {
                            if ($get_settings['button' . $it . '_hint'] == "hint") {
                                if (empty($btn_n)) {
                                    $btn_t = 'line';
                                    $btn_n = $get_settings['button' . $it . '_hline'] . '!silent';
                                    $btn_opt = '';
                                } else {
                                    // $btn_opt .= ',' . $get_settings['button' . $it . '_hline'] . $this->hint_context['default'];
                                    $btn_opt .= ',' . $get_settings['button' . $it . '_hline'];
                                }
                            }
                        }
                        break;
                    case 'adv.line':
                        $btn_t = 'line';
                        $btn_n = (string) $get_settings['button' . $it . '_line'];
                        $btn_n .= '@' . (string) $get_settings['button' . $it . '_advline'];
                        $btn_opt = (string) $get_settings['button' . $it . '_advopt'];

                        break;
                    case 'line':
                    case 'silent':
                        if (isset($get_settings['button' . $it . '_line'])) {
                            $btn_n = (string) $get_settings['button' . $it . '_line'];
                            if ($it > 0) {
                                if ($btn_t == 'silent') {
                                    $btn_n .= '!silent';
                                    $btn_t = 'line';
                                }
                            }
                        } else {
                            $btn_t = 'empty';
                            $btn_n = '';
                        }
                        break;
                    case 'empty':
                        $btn_t = 'empty';
                        break;
                }
                if (!empty($btn_t)) {
                    $res[] = array('ref' => $ref_id, 'reftype' => $ref_type, 'instance' => (string) ($it + 1), 'type' => $btn_t, 'name' => $btn_n, 'options' => $btn_opt);
                }
            }
        }
        return $res;
    }

    function saveSccpDevice($get_settings, $validateonly = false) {
        $hdr_prefix = 'sccp_hw_';
        $hdr_arprefix = 'sccp_hw-ar_';

        $save_buttons = array();
        $save_settings = array();
        $save_codec = array();
        $name_dev = '';
        $db_field = $this->dbinterface->HWextension_db_SccpTableData("get_columns_sccpdevice");
        $hw_id = (empty($get_settings['sccp_deviceid'])) ? 'new' : $get_settings['sccp_deviceid'];
        $hw_type = (empty($get_settings['sccp_device_typeid'])) ? 'sccpdevice' : $get_settings['sccp_device_typeid'];
        $update_hw = ($hw_id == 'new') ? 'add' : 'clear'; // Possible values are delete, replace, add, clear.
        $hw_prefix = 'SEP';
        if (!empty($get_settings[$hdr_prefix . 'type'])) {
            $value = $get_settings[$hdr_prefix . 'type'];
            if (strpos($value, 'ATA') !== false) {
                $hw_prefix = 'ATA';
            }
            if (strpos($value, 'VG') !== false) {
                $hw_prefix = 'VG';
            }
        }
        foreach ($db_field as $data) {
            $key = (string) $data['Field'];
            $value = "";
            switch ($key) {
                case 'name':
                    if (!empty($get_settings[$hdr_prefix . 'mac'])) {
                        $value = $get_settings[$hdr_prefix . 'mac'];
                        $value = strtoupper(str_replace(array('.', '-', ':'), '', $value)); // Delete mac Seporated from string
                        $value = sprintf("%012s", $value);
                        if ($hw_prefix == 'VG') {
                            $value = $hw_prefix . $value . '0';
                        } else {
                            $value = $hw_prefix . $value;
                        }
                        $name_dev = $value;
                    }
                    break;
                case 'disallow':
                    $value = $get_settings['sccp_disallow'];
                    break;

                case 'allow':
                    $i = 0;
                    if (!empty($get_settings['voicecodecs'])) {
                        foreach ($get_settings['voicecodecs'] as $keycodeс => $valcodeс) {
                            $save_codec[$i] = $keycodeс;
                            $i++;
                        };
                        $value = implode(";", $save_codec);
                    } else {
                        $value = 'all'; // Bug If not System Codecs
                    }
                    break;
                case 'phonecodepage':
                    $value = 'null';
                    if (!empty($get_settings[$hdr_prefix . 'devlang'])) {
                        $lang_data = $this->extconfigs->getextConfig('sccp_lang', $get_settings[$hdr_prefix . 'devlang']);
                        if (!empty($lang_data)) {
                            $value = $lang_data['codepage'];
                        }
                    }
                    break;
                case '_hwlang':
                    if (empty($get_settings[$hdr_prefix . 'netlang']) || empty($get_settings[$hdr_prefix . 'devlang'])) {
                        $value = 'null';
                    } else {
                        $value = $get_settings[$hdr_prefix . 'netlang'] . ':' . $get_settings[$hdr_prefix . 'devlang'];
                    }
                    break;
                default:
                    if (!empty($get_settings[$hdr_prefix . $key])) {
                        $value = $get_settings[$hdr_prefix . $key];
                    }
                    if (!empty($get_settings[$hdr_arprefix . $key])) {
                        $arr_data = '';
                        $arr_clear = false;
                        foreach ($get_settings[$hdr_arprefix . $key] as $vkey => $vval) {
                            $tmp_data = '';
                            foreach ($vval as $vkey => $vval) {
                                switch ($vkey) {
                                    case 'inherit':
                                        if ($vval == 'on') {
                                            $arr_clear = true;
                                            // Злобный ХАК ?!TODO!?
                                            if ($key == 'permit') {
                                                $save_settings['deny'] = 'NONE';
                                            }
                                        }
                                        break;
                                    case 'internal':
                                        if ($vval == 'on') {
                                            $tmp_data .= 'internal;';
                                        }
                                        break;
                                    default:
                                        $tmp_data .= $vval . '/';
                                        break;
                                }
                            }
                            if (strlen($tmp_data) > 2) {
                                while (substr($tmp_data, -1) == '/') {
                                    $tmp_data = substr($tmp_data, 0, -1);
                                }
                                $arr_data .= $tmp_data . ';';
                            }
                        }
                        while (substr($arr_data, -1) == ';') {
                            $arr_data = substr($arr_data, 0, -1);
                        }
                        if ($arr_clear) {
                            $value = 'NONE';
                        } else {
                            $value = $arr_data;
                        }
                    }
            }
            if (!empty($value)) {
                $save_settings[$key] = $value;
            }
        }
        $this->dbinterface->write('sccpdevice', $save_settings, 'replace');
        $save_buttons = $this->getPhoneButtons($get_settings, $name_dev, $hw_type);
        $this->dbinterface->write('sccpbuttons', $save_buttons, $update_hw, '', $name_dev);
        $this->createSccpDeviceXML($name_dev);
        if ($hw_id == 'new') {
            $this->aminterface->sccpDeviceReset($name_dev, 'reset');
        } else {
            $this->aminterface->sccpDeviceReset($name_dev, 'restart');
        }

        return $save_settings;
    }

    function handleSubmit($get_settings, $validateonly = false) {
        $hdr_prefix = 'sccp_';
        $hdr_arprefix = 'sccp-ar_';
        $save_settings = array();
        $save_codec = array();
        $integer_msg = _("%s must be a non-negative integer");
        $errors = array();
        $i = 0;
        foreach ($get_settings as $key => $value) {
            $pos = strpos($key, $hdr_prefix);
            if ($pos !== false) {
                $key1 = substr_replace($key, '', 0, strlen($hdr_prefix));
                if (!empty($this->sccpvalues[$key1])) {
                    if (!($this->sccpvalues[$key1]['data'] == $value)) {
                        $save_settings[] = array('keyword' => $this->sccpvalues[$key1]['keyword'], 'data' => $value,
                            'seq' => $this->sccpvalues[$key1]['seq'], 'type' => $this->sccpvalues[$key1]['type']);
                    }
                }
            }
            $pos = strpos($key, $hdr_arprefix);
            if ($pos !== false) {
                $key1 = substr_replace($key, '', 0, strlen($hdr_arprefix));
                $arr_data = '';
                if (!empty($this->sccpvalues[$key1])) {
                    foreach ($value as $vkey => $vval) {
                        $tmp_data = '';
                        foreach ($vval as $vkey => $vval) {
                            switch ($vkey) {
                                case 'inherit':
                                case 'internal':
                                    if ($vval == 'on') {
                                        $tmp_data .= 'internal;';
                                    }
                                    break;
                                default:
                                    $tmp_data .= $vval . '/';
                                    break;
                            }
                        }
                        if (strlen($tmp_data) > 2) {
                            while (substr($tmp_data, -1) == '/') {
                                $tmp_data = substr($tmp_data, 0, -1);
                            }
                            $arr_data .= $tmp_data . ';';
                        }
                    }
                    while (substr($arr_data, -1) == ';') {
                        $arr_data = substr($arr_data, 0, -1);
                    }
                    if (!($this->sccpvalues[$key1]['data'] == $arr_data)) {
                        $save_settings[] = array('keyword' => $this->sccpvalues[$key1]['keyword'], 'data' => $arr_data,
                            'seq' => $this->sccpvalues[$key1]['seq'], 'type' => $this->sccpvalues[$key1]['type']);
                    }
                }
            }
            switch ($key) {
                case 'voicecodecs':
                case 'vcodec':
                    foreach ($value as $keycodeс => $valcodeс) {
                        $save_codec[$i] = $keycodeс;
                        $i++;
                    };
                    $tmpv = implode(";", $save_codec);
                    if ($tmpv !== $this->sccpvalues['allow']['data']) {
                        $save_settings[] = array('keyword' => 'allow', 'data' => $tmpv,
                            'seq' => $this->sccpvalues['allow']['seq'],
                            'type' => $this->sccpvalues['allow']['type']);
                    }
                    break;

                case 'sccp_ntp_timezone':
                    $tz_id = $value;
                    $TZdata = $this->extconfigs->getextConfig('sccp_timezone', $tz_id);
                    if (!empty($TZdata)) {
                        $value = $TZdata['offset']/60;
                        if (!($this->sccpvalues['tzoffset']['data'] == $value)) {
                            $save_settings[] = array('keyword' => 'tzoffset', 'data' => $value,
                                'seq' => '98',
                                'type' => '2');
                        }
                    }
                    break;
            }
        }
        if (!empty($save_settings)) {
            $this->saveSccpSettings($save_settings);
            $this->sccpvalues = $this->dbinterface->get_db_SccpSetting();
        }
        $this->createDefaultSccpConfig(); // Rewrite Config.
        $save_settings[] = array('status' => true);
        return $save_settings;
    }

    function handleRoamingUsers($get_settings, $validateonly = false) {
        $hdr_prefix = 'sccp_ru_';
        $hdr_arprefix = 'sccp_ru-ar_';

        $save_buttons = array();
        $save_settings = array();
        $name_dev = '';
        $db_field = $this->dbinterface->HWextension_db_SccpTableData("get_columns_sccpuser");
        $hw_prefix = 'SEP';
        $name_dev = $get_settings[$hdr_prefix . 'id'];
        $save_buttons = $this->getPhoneButtons($get_settings, $name_dev, 'sccpline');

        foreach ($db_field as $data) {
            $key = (string) $data['Field'];
            $value = "";
            switch ($key) {
                case 'name':
                    $value = $name_dev;
                    break;
                case '_hwlang':
                    if (empty($get_settings[$hdr_prefix . 'netlang']) || empty($get_settings[$hdr_prefix . 'devlang'])) {
                        $value = 'null';
                    } else {
                        $value = $get_settings[$hdr_prefix . 'netlang'] . ':' . $get_settings[$hdr_prefix . 'devlang'];
                    }
                    break;
                default:
                    if (!empty($get_settings[$hdr_prefix . $key])) {
                        $value = $get_settings[$hdr_prefix . $key];
                    }
                    if (!empty($get_settings[$hdr_arprefix . $key])) {
                        $arr_data = '';
                        $arr_clear = false;
                        foreach ($get_settings[$hdr_arprefix . $key] as $vkey => $vval) {
                            $tmp_data = '';
                            foreach ($vval as $vkey => $vval) {
                                switch ($vkey) {
                                    case 'inherit':
                                        if ($vval == 'on') {
                                            $arr_clear = true;
                                            // Злобный ХАК ?!TODO!?
                                            if ($key == 'permit') {
                                                $save_settings['deny'] = 'NONE';
                                            }
                                        }
                                        break;
                                    case 'internal':
                                        if ($vval == 'on') {
                                            $tmp_data .= 'internal;';
                                        }
                                        break;
                                    default:
                                        $tmp_data .= $vval . '/';
                                        break;
                                }
                            }
                            if (strlen($tmp_data) > 2) {
                                while (substr($tmp_data, -1) == '/') {
                                    $tmp_data = substr($tmp_data, 0, -1);
                                }
                                $arr_data .= $tmp_data . ';';
                            }
                        }
                        while (substr($arr_data, -1) == ';') {
                            $arr_data = substr($arr_data, 0, -1);
                        }
                        if ($arr_clear) {
                            $value = 'NONE';
                        } else {
                            $value = $arr_data;
                        }
                    }
            }
            if (!empty($value)) {
                $save_settings[$key] = $value;
            }
        }
        $this->dbinterface->write('sccpuser', $save_settings, 'replace', 'name');
        $this->dbinterface->write('sccpbuttons', $save_buttons, 'delete', '', $name_dev); //standardise to delete
        return $save_buttons;
        // Why is there a second return here???????
        return $save_settings;
    }

    public function getMyConfig($var = null, $id = "noid") {
        switch ($var) {
            case "voicecodecs":
                $val = explode(";", $this->sccpvalues['allow']['data']);
                $final = array();
                $i = 1;
                foreach ($val as $value) {
                    $final[$value] = $i;
                    $i++;
                }
                break;
            case "softkeyset":
                $final = array();
                $i = 0;
                if ($id == "noid") {
                    foreach ($this->sccp_conf_init as $key => $value) {
                        if ($this->sccp_conf_init[$key]['type'] == 'softkeyset') {
                            $final[$i] = $value;
                            $i++;
                        }
                    }
                } else {
                    if (!empty($this->sccp_conf_init[$id])) {
                        if ($this->sccp_conf_init[$id]['type'] == 'softkeyset') {
                            $final = $this->sccp_conf_init[$id];
                        }
                    }
                }

                break;
        }
        return $final;
    }

    public function getCodecs($type, $showDefaults = false) {
        $allSupported = array();
        $Sccp_Codec = array('alaw', 'ulaw', 'g722', 'g723', 'g726', 'g729', 'gsm', 'h264', 'h263', 'h261');
        switch ($type) {
            case 'audio':
                $lcodecs = $this->getMyConfig('voicecodecs');
                $allCodecs = $this->FreePBX->Codecs->getAudio();
                break;
            case 'video':
                $lcodecs = $this->getMyConfig('voicecodecs');
                $allCodecs = $this->FreePBX->Codecs->getVideo();
                break;
            case 'text':
                $lcodecs = $this->getConfig('textcodecs');
                $allCodecs = $this->FreePBX->Codecs->getText(true);
                break;
            case 'image':
                $lcodecs = $this->getConfig('imagecodecs');
                $allCodecs = $this->FreePBX->Codecs->getImage(true);
                break;
            default:
                throw new Exception(_('Unknown Type'));
                break;
        }
        foreach ($allCodecs as $c => $v) {
            if (in_array($c, $Sccp_Codec)) {
                $allSupported[$c] = $v;
            }
        }
        if (empty($lcodecs) || (!is_array($lcodecs))) {
            if (empty($allSupported)) {
                $lcodecs = $allCodecs;
            } else {
                $lcodecs = $allSupported;
            }
        } else {
            foreach ($lcodecs as $c => $v) {
                if (isset($allSupported[$c])) {
                    $codecs[$c] = true;
                }
            }
        }
        if ($showDefaults) {
            foreach ($allSupported as $c => $v) {
                if (!isset($codecs[$c])) {
                    $codecs[$c] = false;
                }
            }

            return $codecs;
        } else {
            //Remove non digits
            $final = array();
            foreach ($codecs as $codec => $order) {
                $order = trim($order);
                if (ctype_digit($order)) {
                    $final[$codec] = $order;
                }
            }
            asort($final);
            return $final;
        }
    }

    /**
     * Update or Set Codecs
     * @param {string} $type           Codec Type
     * @param {array} $codecs=array() The codecs with order, if blank set defaults
     */
    public function setCodecs($type, $codecs = array()) {
        $default = empty($codecs) ? true : false;
        switch ($type) {
            case 'audio':
                $codecs = $default ? $this->FreePBX->Codecs->getAudio(true) : $codecs;
                $this->setConfig("voicecodecs", $codecs);
                break;
            case 'video':
                $codecs = $default ? $this->FreePBX->Codecs->getVideo(true) : $codecs;
                $this->setConfig("videocodecs", $codecs);
                break;
            case 'text':
                $codecs = $default ? $this->FreePBX->Codecs->getText(true) : $codecs;
                $this->setConfig("textcodecs", $codecs);
                break;
            case 'image':
                $codecs = $default ? $this->FreePBX->Codecs->getImage(true) : $codecs;
                $this->setConfig("imagecodecs", $codecs);
                break;
            default:
                throw new Exception(_('Unknown Type'));
                break;
        }
        return true;
    }

    /**
     * Retrieve Active Codecs
     * return fiends Lag pack
     */

    private function initTftpLang() {
        $result = array();
        if (empty($this->sccppath["tftp_path"]) || empty($this->sccppath["tftp_lang_path"])) {
            return;
        }
        $dir = $this->sccppath["tftp_lang_path"];

        $cdir = scandir($dir);
        foreach ($cdir as $key => $value) {
            if (!in_array($value, array(".", ".."))) {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $value)) {
                    $filename = $dir . DIRECTORY_SEPARATOR . $value . DIRECTORY_SEPARATOR . $this->SCCP_LANG_DICTIONARY;
                    if (file_exists($filename)) {
                        $lang_ar = $this->extconfigs->getextConfig('sccp_lang');
                        foreach ($lang_ar as $lang_key => $lang_value) {
                            if ($lang_value['locale'] == $value) {
                                $result[$lang_key] = $value;
                            }
                        }
//                        $result[] = $value;
                    }
                }
            }
        }
        $this->tftpLang = $result;
    }

    /*
     *    Check tftp/xml file path and permissions
     */

    private function initializeTFtpLanguagePath() {
        $dir = $this->sccppath["tftp_lang_path"];
        foreach ($this->extconfigs->getextConfig('sccp_lang') as $lang_key => $lang_value) {
            $filename = $dir . DIRECTORY_SEPARATOR . $lang_value['locale'];
            if (!file_exists($filename)) {
                if (!mkdir($filename, 0777, true)) {
                    die('Error create lang dir');
                }
            }
        }
    }

    /*
     *    Check file paths and permissions
     */

    // !TODO!: -TODO-: This function is getting a little big. Might be possible to sperate tftp work into it's own file/class. Initially, you need to remove the not working section and commented out section
    function initializeSccpPath() {
        global $db;
        global $amp_conf;
        $driver_revision = array('0' => '', '430' => '.v431', '431' => '.v432', '432' => '.v432', '433' => '.v433' . $this->sccp_branch);

        $confDir = $amp_conf["ASTETCDIR"];
        if (empty($this->sccppath["asterisk"])) {
            if (strlen($confDir) < 1) {
                $this->sccppath["asterisk"] = "/etc/asterisk";
            } else {
                $this->sccppath["asterisk"] = $confDir;
            }
        }
        $ver_id = $this->aminterface->get_compatible_sccp();
        if (!empty($this->sccpvalues['SccpDBmodel'])) {
            $ver_id = $this->sccpvalues['SccpDBmodel']['data'];
        }

        $driver = $this->FreePBX->Core->getAllDriversInfo();
        // Below is always set to replace; good for Develop, but needs to be updated for release
        $sccp_driver_replace = '';
        if (empty($driver['sccp'])) {
            $sccp_driver_replace = 'yes';
        } else {
            if (empty($driver['sccp']['Version'])) {
                $sccp_driver_replace = 'yes';
            } else {
                if ($driver['sccp']['Version'] != $this->sccp_driver_ver . $driver_revision[$ver_id]) {
                    $sccp_driver_replace = 'yes';
                }
            }
        }

        $this->sccpvalues['sccp_compatible'] = array('keyword' => 'sccp_compatible', 'data' => $ver_id, 'type' => '1', 'seq' => '99');
        $this->sccppath = $this->extconfigs->validate_init_path($confDir, $this->sccpvalues, $sccp_driver_replace);
        $driver = $this->FreePBX->Core->getAllDriversInfo(); // Check that Sccp Driver has been updated by above

        $read_config = $this->cnf_read->getConfig('sccp.conf');
        $this->sccp_conf_init['general'] = $read_config['general'];
        foreach ($read_config as $key => $value) {
            if (isset($read_config[$key]['type'])) { // copy soft key
                if ($read_config[$key]['type'] == 'softkeyset') {
                    $this->sccp_conf_init[$key] = $read_config[$key];
                }
            }
        }

        $hint = $this->aminterface->core_list_hints();
        foreach ($hint as $key => $value) {
            if ($this->hint_context['default'] != $value) {
                $this->hint_context[$key] = $value;
            }
        }
    }

    /*
     *      Soft Key
     */

    function createSccpXmlSoftkey() {
        foreach ($this->aminterface->sccp_list_keysets() as $keyl => $vall) {
            $this->xmlinterface->create_xmlSoftkeyset($this->sccp_conf_init, $this->sccppath, $keyl);
        }
    }

    /*
     *      DialPlan
     */

    function getDialPlanList() {
        $dir = $this->sccppath["tftp_dialplan"] . '/dial*.xml';
        $base_len = strlen($this->sccppath["tftp_dialplan"]) + 1;
        $res = glob($dir);
        $dp_list = array();
        foreach ($res as $key => $value) {
            $res[$key] = array('id' => substr($value, $base_len, -4), 'file' => substr($value, $base_len));
        }

        return $res;
    }

    function getDialPlan($get_file) {
        $file = $this->sccppath["tftp_dialplan"] . '/' . $get_file . '.xml';
        if (file_exists($file)) {
//            $load_xml_data = simplexml_load_file($file);

            $fileContents = file_get_contents($file);
            $fileContents = str_replace(array("\n", "\r", "\t"), '', $fileContents);
            $fileContents = trim(str_replace('"', "'", $fileContents));
            $fileContents = strtolower($fileContents);
            $res = (array) simplexml_load_string($fileContents);
        }
        return $res;
    }

    function deleteDialPlan($get_file) {
        if (!empty($get_file)) {
            $file = $this->sccppath["tftp_dialplan"] . '/' . $get_file . '.xml';
            if (file_exists($file)) {
                $res = unlink($file);
            }
        }
        return $res;
    }

    function saveDialPlan($get_settings) {

        $confDir = $this->sccppath["tftp_dialplan"];
        return $this->xmlinterface->saveDialPlan($confDir, $get_settings);
    }

    /*
     *      Update Butons Labels on mysql DB
     */

    private function updateSccpButtons($hw_list = array()) {

        $save_buttons = array();
        if (!empty($hw_list)) {
            $buton_list = array();
            foreach ($hw_list as $value) {
                $buton_tmp = $this->dbinterface->HWextension_db_SccpTableData("get_sccpdevice_buttons", array('buttontype' => 'speeddial', 'id' => $value['name']));
                if (!empty($buton_tmp)) {
                    $buton_list = array_merge($buton_list, $buton_tmp);
                }
            }
        } else {
            $buton_list = $this->dbinterface->HWextension_db_SccpTableData("get_sccpdevice_buttons", array('buttontype' => 'speeddial'));
        }
        if (empty($buton_list)) {
            return array('Response' => ' 0 buttons found ', 'data' => '');
        }
        $copy_fld = array('ref', 'reftype', 'instance', 'buttontype');
        $user_list = $user_list = $this->dbinterface->get_db_SccpTableByID("SccpExtension", array(), 'name');
        foreach ($buton_list as $value) {
            $btn_opt = explode(',', $value['options']);
            $btn_id = $btn_opt[0];
            if (!empty($user_list[$btn_id])) {
                if ($user_list[$btn_id]['label'] != $value['name']) {
                    $btn_data['name'] = $user_list[$btn_id]['label'];
                    foreach ($copy_fld as $ckey) {
                        $btn_data[$ckey] = $value[$ckey];
                    }
                    $save_buttons[] = $btn_data;
                }
            }
        }
        if (empty($save_buttons)) {
            return array('Response' => 'No update required', 'data' => ' 0 - records ');
        }
        $res = $this->dbinterface->write('sccpbuttons', $save_buttons, 'replace', '', '');
        return array('Response' => 'Update records :' . count($save_buttons), 'data' => $res);
    }

    /*
     *      Save Config Value to mysql DB
     */

    private function saveSccpSettings($save_value = array()) {
//        global $db;
//        global $amp_conf;

//        $save_settings = array();
        if (empty($save_value)) {
            $this->dbinterface->write('sccpsettings', $this->sccpvalues, 'replace'); //Change to replace as clearer
        } else {
            $this->dbinterface->write('sccpsettings', $save_value, 'update');
        }
        return true;
    }

    /*
     *          Create XMLDefault.cnf.xml
     */

    function createDefaultSccpXml() {
        $data_value = array();
        foreach ($this->sccpvalues as $key => $value) {
            $data_value[$key] = $value['data'];
        }
        $data_value['server_if_list'] = $this->getIpInformation('ip4');
        $model_information = $this->getSccpModelInformation($get = "enabled", $validate = false); // Get Active

        if (empty($model_information)) {
            $model_information = $this->getSccpModelInformation($get = "all", $validate = false); // Get All
        }

        $lang_data = $this->extconfigs->getextConfig('sccp_lang');
        $data_value['tftp_path'] = $this->sccppath["tftp_path"];

        $this->xmlinterface->create_default_XML($this->sccppath["tftp_path_store"], $data_value, $model_information, $lang_data);
    }

    /*
     *          Create  (SEP) dev_ID.cnf.xml
     */

    function createSccpDeviceXML($dev_id = '') {

        if (empty($dev_id)) {
            return false;
        }
        $sccp_native = true;
        $data_value = array();
        $dev_line_data = null;

        $dev_config = $this->dbinterface->HWextension_db_SccpTableData("get_sccpdevice_byid", array('id' => $dev_id));
        // Support Cisco Sip Device
        if (!empty($dev_config['type'])) {
            if (strpos($dev_config['type'], 'sip') !== false) {
                $sccp_native = false;
                $tmp_bind = $this->sipconfigs->getSipConfig();
                $dev_ext_config = $this->dbinterface->HWextension_db_SccpTableData("SccpDevice", array('name' => $dev_id, 'fields' => 'sip_ext'));
                $data_value = array_merge($data_value, $dev_ext_config);
                $data_tmp = explode(';', $dev_ext_config['sip_lines']);
                $data_value['sbind'] = array();
                foreach ($data_tmp as $value) {
                    $tmp_line = explode(',', $value);
                    switch ($tmp_line[0]) {
                        case 'line':
                            $dev_line_data = $this->sipconfigs->get_db_sip_TableData('DeviceById', array('id' => $tmp_line[1]));
                            $f_linetype = ($dev_line_data['dial'] == 'PJSIP') ? 'pjsip' : 'sip';
                            $dev_line_data['sbind'] = $tmp_bind[$f_linetype];
                            if ((!$this->array_key_exists_recursive('udp', $tmp_bind[$f_linetype])) && (!$this->array_key_exists_recursive('tcp', $tmp_bind[$f_linetype]))) {
                                print_r("Wrong sip server Config ! Not enabled UDP or TCP protocol");
                                die();
                                return -1;
                            }

                            if (!empty($dev_line_data)) {
                                $data_value['siplines'][] = $dev_line_data;
                            }
                            if ($tmp_line[2] == 'default') {
                                $data_value['sbind'] = $tmp_bind[$f_linetype];
                            }
                            break;
                        case 'speeddial':
                            $data_value['speeddial'][] = array("name" => $tmp_line[1], "dial" => $tmp_line[2]);
                            break;
                        default:
                            $data_value['sipfunctions'][] = $tmp_line;
                            break;
                    }
                }
            }
        }

        foreach ($this->sccpvalues as $key => $value) {
            $data_value[$key] = $value['data'];
        }
        //Get Cisco Code only Old Device
        $data_value['ntp_timezone_id'] = $this->extconfigs->getextConfig('sccp_timezone', $data_value['ntp_timezone']); // Old Cisco Device
        // $data_value['ntp_timezone_id'] = $data_value['ntp_timezone']; // New Cisco Device !
        // $data_value['ntp_timezone_id'] = // SPA Cisco Device !
        $data_value['server_if_list'] = $this->getIpInformation('ip4');
        $dev_config = array_merge($dev_config, $this->sccppath);
        $dev_config['tftp_firmware'] = '';
        $dev_config['addon_info'] = array();
        if (!empty($dev_config['addon'])) {
            $hw_addon = explode(',', $dev_config['addon']);
            foreach ($hw_addon as $key) {
                $hw_data = $this->getSccpModelInformation('byid', false, "all", array('model' => $key));
                $dev_config['addon_info'][$key] = $hw_data[0]['loadimage'];
            }
        }

        $lang_data = $this->extconfigs->getextConfig('sccp_lang');
        if (!$sccp_native) {
            return $this->xmlinterface->create_SEP_SIP_XML($this->sccppath["tftp_path_store"], $data_value, $dev_config, $dev_id, $lang_data);
        }
        return $this->xmlinterface->create_SEP_XML($this->sccppath["tftp_path_store"], $data_value, $dev_config, $dev_id, $lang_data);
    }

    function deleteSccpDeviceXML($dev_id = '') {
        if (empty($dev_id)) {
            return false;
        }
        if ($dev_id == 'all') {
            $xml_name = $this->sccppath["tftp_path_store"] . '/SEP*.cnf.xml';
            array_map("unlink", glob($xml_name));
            $xml_name = $this->sccppath["tftp_path_store"] . '/ATA*.cnf.xml';
            array_map("unlink", glob($xml_name));
            $xml_name = $this->sccppath["tftp_path_store"] . '/VG*.cnf.xml';
            array_map("unlink", glob($xml_name));
        } else {
            if (!strpos($dev_id, 'SEP')) {
                return false;
            }
            $xml_name = $this->sccppath["tftp_path_store"] . '/' . $dev_id . '.cnf.xml';
            if (file_exists($xml_name)) {
                unlink($xml_name);
            }
        }
    }

    private function createSccpBackup() {
        global $amp_conf;
        $dir_info = array();
        $backup_files = array($amp_conf['ASTETCDIR'] . '/sccp', $amp_conf['ASTETCDIR'] . '/extensions', $amp_conf['ASTETCDIR'] . '/extconfig',
            $amp_conf['ASTETCDIR'] . '/res_config_mysql', $amp_conf['ASTETCDIR'] . '/res_mysql');
        $backup_ext = array('.conf', '_additional.conf', '_custom.conf');
        $backup_info = $this->sccppath["tftp_path"] . '/sccp_dir.info';

        $result = $this->dbinterface->dump_sccp_tables($this->sccppath["tftp_path"], $amp_conf['AMPDBNAME'], $amp_conf['AMPDBUSER'], $amp_conf['AMPDBPASS']);
        $dir_info['asterisk'] = $this->findAllFiles($amp_conf['ASTETCDIR']);
        $dir_info['tftpdir'] = $this->findAllFiles($this->sccppath["tftp_path"]);
        $dir_info['driver'] = $this->FreePBX->Core->getAllDriversInfo();
        $dir_info['core'] = $this->aminterface->getSCCPVersion();
        $dir_info['realtime'] = $this->aminterface->getRealTimeStatus();
        $dir_info['extconfigs'] = $this->extconfigs->info();
        $dir_info['dbinterface'] = $this->dbinterface->info();
        $dir_info['XML'] = $this->xmlinterface->info();

        $fh = fopen($backup_info, 'w');
        $dir_str = "Begin JSON data ------------\r\n";
        fwrite($fh, $dir_str);
        fwrite($fh, json_encode($dir_info));
        $dir_str = "\r\n\r\nBegin TEXT data ------------\r\n";
        foreach ($dir_info['asterisk'] as $data) {
            $dir_str .= $data . "\r\n";
        }
        foreach ($dir_info['tftpdir'] as $data) {
            $dir_str .= $data . "\r\n";
        }
        fputs($fh, $dir_str);
        fclose($fh);

        $zip = new \ZipArchive();
        $filename = $result . "." . gethostname() . ".zip";
        if ($zip->open($filename, \ZIPARCHIVE::CREATE)) {
            $zip->addFile($result);
            $zip->addFile($backup_info);
            foreach ($backup_files as $file) {
                foreach ($backup_ext as $b_ext) {
                    if (file_exists($file . $b_ext)) {
                        $zip->addFile($file . $b_ext);
                    }
                }
            }
            $zip->close();
        }
        unlink($backup_info);
        unlink($result);
        return $filename;
    }

    function createDefaultSccpConfig() {
        // Make sccp.conf data
        // [general]
        foreach ($this->sccpvalues as $key => $value) {
            if ($value['seq'] == 0) {
                switch ($key) {
                    case "allow":
                    case "disallow":
                    case "deny":
                        $this->sccp_conf_init['general'][$key] = explode(';', $value['data']);
                        break;
                    case "localnet":
                    case "permit":
                        $content = $value['data'];
                        if (strpos($content, 'internal') !== false) {
                            $content = str_replace(';0.0.0.0/0.0.0.0', '', $value['data']);
                        }
                        $this->sccp_conf_init['general'][$key] = explode(';', $content);
                        break;
                    case "devlang":
                        $lang_data = $this->extconfigs->getextConfig('sccp_lang', $value['data']);
                        if (!empty($lang_data)) {
                            $this->sccp_conf_init['general']['phonecodepage'] = $lang_data['codepage'];
                        }
                        break;
                    case "netlang": // Remove Key
                    case "tftp_path":
                    case "sccp_compatible":
                        break;
                    default:
                        if (!empty($value['data'])) {
                            $this->sccp_conf_init['general'][$key] = $value['data'];
                        }
                }
            }
        }
        // [Namesoftkeyset]
        // type=softkeyset
        //
        // ----- It is a very bad idea to add an external configuration file "sccp_custom.conf" !!!!
        // This will add problems when solving problems caused by unexpected solutions from users.
        //
        if (file_exists($this->sccppath["asterisk"] . "/sccp_custom.conf")) {
            $this->sccp_conf_init['HEADER'] = array(
                ";                                                                                ;",
                ";  It is a very bad idea to add an external configuration file !!!!              ;",
                ";  This will add problems when solving problems caused by unexpected solutions   ;",
                ";  from users.                                                                   ;",
                ";--------------------------------------------------------------------------------;",
                "#include sccp_custom.conf"
            );
        }
        // ----- It is a very bad idea to add an external configuration file "sccp_custom.conf" !!!!

        $this->cnf_wr->writeConfig('sccp.conf', $this->sccp_conf_init);
    }

    function getSccpModelInformation($get = "all", $validate = false, $format_list = "all", $filter = array()) {
        // $file_ext = array('.loads', '.LOADS', '.sbn', '.SBN', '.bin', '.BIN','.zup','.ZUP');
        $file_ext = array('.loads', '.sbn', '.bin', '.zup');
        // $dir = $this->sccppath["tftp_path"];
        $dir = $this->sccppath["tftp_firmware_path"];
        $dir_tepl = $this->sccppath["tftp_templates"];

        $search_mode = '';
        if (!empty($this->sccpvalues['tftp_rewrite'])) {
            $search_mode = $this->sccpvalues['tftp_rewrite']['data'];
            switch ($search_mode) {
                case 'pro':
                case 'on':
                case 'internal':
                    $dir_list = $this->findAllFiles($dir, $file_ext, 'fileonly');
                    break;
                case 'off':
                default: // Place in root TFTP dir
                    $dir_list = $this->findAllFiles($dir, $file_ext);
                    break;
            }
        } else {
            $dir_list = $this->findAllFiles($dir, $file_ext, 'fileonly');
        }
        $raw_settings = $this->dbinterface->getDb_model_info($get, $format_list, $filter);
        if ($validate) {
            for ($i = 0; $i < count($raw_settings); $i++) {
                $raw_settings[$i]['validate'] = '-;-';
                if (!empty($raw_settings[$i]['loadimage'])) {
                    $raw_settings[$i]['validate'] = 'no;';
                    if (((strtolower($raw_settings[$i]['vendor']) == 'cisco') || (strtolower($raw_settings[$i]['vendor']) == 'cisco-sip')) && !empty($dir_list)) {
                        foreach ($dir_list as $filek) {
                            switch ($search_mode) {
                                case 'pro':
                                case 'on':
                                case 'internal':
                                    if (strpos(strtolower($filek), strtolower($raw_settings[$i]['loadimage'])) !== false) {
                                        $raw_settings[$i]['validate'] = 'yes;';
                                    }
                                    break;
                                case 'internal2':
                                    break;
                                case 'off':
                                default: // Place in root TFTP dir
                                    if (strpos(strtolower($filek), strtolower($dir . '/' . $raw_settings[$i]['loadimage'])) !== false) {
                                        $raw_settings[$i]['validate'] = 'yes;';
                                    }
                                    break;
                            }
                        }
                    }
                } else {
                    $raw_settings[$i]['validate'] = '-;';
                }
                if (!empty($raw_settings[$i]['nametemplate'])) {
                    $file = $dir_tepl . '/' . $raw_settings[$i]['nametemplate'];
                    if (file_exists($file)) {
                        $raw_settings[$i]['validate'] .= 'yes';
                    } else {
                        $raw_settings[$i]['validate'] .= 'no';
                    }
                } else {
                    $raw_settings[$i]['validate'] .= '-';
                }
            }
        }
        return $raw_settings;
    }

    function getHintInformation($sort = true, $filter = array()) {
        $res = array();
        $default_hint = '@ext-local';

        if (empty($res)) {
            // Old Req get all hints
            $tmp_data = $this->aminterface->core_list_all_hints();
            foreach ($tmp_data as $value) {
                $res[$value] = array('key' => $value, 'exten' => $this->before('@', $value), 'label' => $value);
            }
        }

        // Update info from sccp_db
        $tmp_data = $this->dbinterface->HWextension_db_SccpTableData('SccpExtension');
        foreach ($tmp_data as $value) {
            $name_l = $value['name'];
            if (!empty($res[$name_l . $default_hint])) {
                $res[$name_l . $default_hint]['exten'] = $name_l;
                $res[$name_l . $default_hint]['label'] = $value['label'];
            } else {
                // if not exist in system hints ..... ???????
                $res[$name_l . $default_hint] = array('key' => $name_l . $default_hint, 'exten' => $name_l, 'label' => $value['label']);
            }
        }
        if (!$sort) {
            return $res;
        }

        foreach ($res as $key => $value) {
            $data_sort[$value['exten']] = $key;
        }
        ksort($data_sort);
        foreach ($data_sort as $key => $value) {
            $res_sort[$value] = $res[$value];
        }

        // Update info from sip DB
        /* !TODO!: Update Hint info from sip DB ??? */
        return $res_sort;
    }
}
?>
