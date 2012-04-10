<?php
//$Copyright$

// no direct access
defined('_JEXEC') or die('Restricted access');

class K2fieldsWidgetkitHelper {
        public static function getWidget($itemId, $field, $type) {
                $fieldId = K2FieldsModelFields::value($field, 'id');
                $name = 'k2fields_auto_for_item_'.$itemId.'_field_'.$fieldId;
                $db = JFactory::getDbo();
                
                $db->setQuery('SELECT id, name, content FROM #__widgetkit_widget WHERE name = '.$db->quote($name));
                
                $rec = $db->loadObject();
                
                if ($rec) {
                        $rec->content = json_decode($rec->content);
                        $rec->settings = $rec->content->settings;
                } else {
                        static $map = array(
                                'gallery' => array('style'=>'default','width'=>'auto','height'=>'auto','autoplay'=>1,'order'=>'default','interval'=>5000,'duration'=>500,'index'=>0,'navigation'=>1,'buttons'=>1,'slices'=>20,'animated'=>'randomSimple','caption_animation_duration'=>500,'lightbox'=>0),
                                'slideshow' => array('style'=>'default','width'=>'auto','height'=>'auto','autoplay'=>1,'order'=>'default','interval'=>5000,'duration'=>500,'index'=>0,'navigation'=>1,'buttons'=>1,'slices'=>20,'animated'=>'randomSimple','caption_animation_duration'=>500),
                                'slideset' => array('style'=>'default','width'=>'auto','height'=>'auto','autoplay'=>1,'interval'=>5000,'index'=>0,'navigation'=>1,'buttons'=>1,'title'=>1,'duration'=>300,'items_per_set'=>3,'effect'=>'slide'),
                                'accordion' => array('style'=>'default','width'=>'auto','order'=>'default','duration'=>500,'index'=>0,'collapseall'=>1,'matchheight'=>1)
                        );

                        $defaultSettings = $map[$type];
                        $settings = array();
                        $isWidthSet = false;

                        foreach ($defaultSettings as $key => $defaultSetting) {
                                $settings[$key] = K2FieldsModelFields::value($field, $key, '');
                                
                                if (empty($settings[$key])) {
                                        if ($key != 'height' || !$isWidthSet) {
                                                $settings[$key] = K2FieldsModelFields::value($field, 'pic'.$key, $defaultSetting);
                                        } else {
                                                $settings[$key] = $defaultSetting;
                                        }
                                }
                                
                                if ($key == 'width' && !empty($settings[$key])) $isWidthSet = true;
                        }

                        $rec = new stdClass();
                        $rec->id = '';
                        $rec->content = '';
                        $rec->settings = $settings;
                        $rec->name = $name;
                }
                
                return $rec;
        } 
        
        public static function save($item, $field, $medias, $caller = 'field') {
                $res = self::isInstalled();
                
                if ($res !== true) return $res;

                $widgetkit = Widgetkit::getInstance();
                $wh = $widgetkit->getHelper('widget');
                $type = K2FieldsModelFields::value($field, 'widgettype', $caller == 'field' ? 'gallery' : 'slideshow');
                $widget = self::getWidget($item->id, $field, $type);
                
                if (empty($medias)) {
                        if (!empty($widget) && !empty($widget->id)) $wh->delete($widget->id);
                        
                        return true;
                }

                $srcs = JprovenUtility::getColumn($medias, K2FieldsMedia::SRCPOS);
                $isConvert = is_object($srcs[0]);
                
                foreach ($srcs as &$src) {
                        if ($isConvert) $src = $src->value;
                        $src = preg_replace('/^(\/|)images/', '', $src);
                }
                
                $captions = JprovenUtility::getColumn($medias, K2FieldsMedia::CAPTIONPOS);
                if ($isConvert) foreach ($captions as &$caption) $caption = $caption->value;
                $captions = array_combine($srcs, $captions);

                $links = array_fill(0, count($medias), '');
                if ($isConvert) foreach ($links as &$link) if (is_object($link)) $link = $link->value;
                $links = array_combine($srcs, $links);
                
                $path = explode('/', $srcs[0]);
                array_pop($path);
                array_shift($path);
                $path = '/'.implode('/', $path);
                
                if ($widget->id) {
                        $settings = get_object_vars($widget->settings);
                        foreach ($settings as $key => $setting) {
                                $setting = K2FieldsModelFields::value($field, 'style', '');
                                if (!empty($setting)) $widget->settings->$key = $setting;
                        }
                }
                
                $gallery = array(
                        'type' => $type, 
                        'id' => $widget->id,
                        'name' => $widget->name, 
                        'settings' => $widget->settings,
                        'style' => K2FieldsModelFields::value($field, 'style', 'default'),
                        'captions' => $captions,
                        'links' => $links,
                        'paths' => array($path)
                );
                
                $wh->save($gallery);
                
                return true;
        }
        
        public static function render($item, $field) {
                $res = self::isInstalled();
                
                if ($res !== true) return $res;
                
                $type = K2FieldsModelFields::value($field, 'widgettype', 'gallery');
                $widget = self::getWidget($item->id, $field, $type);
                $keepSynch = K2FieldsModelFields::value($field, 'widgetsynch', false);
                
                if (empty($widget->id) || $keepSynch) {
                        $model = JModel::getInstance('fields', 'K2FieldsModel');
                        $fieldId = K2FieldsModelFields::value($field, 'id');
                        $medias = $model->itemValues($item->id, array($fieldId));
                        
                        if (empty($medias)) return;
                        
                        $medias = $medias[$fieldId];
                        $medias = JprovenUtility::chunkArray($medias, 'listindex');
                        self::save($item, $field, $medias);
                        $widget = self::getWidget($item->id, $field, $type);
                }
                
                $widgetkit = Widgetkit::getInstance();
                
                $wh = $widgetkit->getHelper('widget');
                return $wh->render($widget->id);                
        }
        
        public static function isInstalled() {
                if (!JFile::exists(JPATH_ADMINISTRATOR.'/components/com_widgetkit/widgetkit.php')) {
                        return K2FieldsMedia::error('Widgetkit is not installed.');
                }
                
                require_once JPATH_ADMINISTRATOR.'/components/com_widgetkit/widgetkit.php';
                
                return true;
        }
}

?>