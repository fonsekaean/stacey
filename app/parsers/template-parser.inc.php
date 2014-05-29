<?php

Class TemplateParser {

  static function find_template($template) {
    if (!file_exists($template)) {
      throw new Exception('\''.$template.'\' template not found.');
    }
    return preg_replace('/.+\//', '', $template);
  }

  static function parse($data, $template) {
    $template = self::find_template($template);

    Twig_Autoloader::register();
    $loader = new Twig_Loader_Filesystem(Config::$templates_folder);
    $cache = is_writable(Config::$cache_folder.'/templates') ? Config::$cache_folder.'/templates' : false;
    $twig = new Twig_Environment($loader, array(
      'cache' => $cache,
      'auto_reload' => true,
      'autoescape' => false
    ));
    $twig->addExtension(new Stacey_Twig_Extension());
	return $twig->render($template, array(
		'page' 	=> $data,
		'toc'	=> self::generateTOC()
	));
  }

  static function generateTOC(){
	$strFolder = Config::$content_folder;

	$arrItems = self::ReadDir($strFolder, '');


	return self::TocHTML($arrItems);

  }

  static function TocHTML($arr, $strClass = ''){
	  $strHTML = "<ul class='$strClass'>";

	  foreach($arr as $arrItem){
		$strHTML .= '<li><a href="' . $arrItem['link'] .  '">' . $arrItem['contents']['title'] . '</a>';
		if(count($arrItem['sub_headings']))
			$strHTML .= self::TocHTML($arrItem['sub_headings'], 'headings');

		if(count($arrItem['children']))
		    $strHTML .= self::TocHTML($arrItem['children'], 'headings');

		$strHTML .= '</li>';
	  }

	  $strHTML .= "</ul>";
	  return $strHTML;
  }

  static function ymlContentsFromFolder($strFolder){
	$strFile = '';
	foreach(scandir($strFolder) as $file){
		if(strpos($file, '.yml') !== false){
			$strFile = $strFolder . '/' . $file;
		}
	}
	if($strFile){
		return sfYaml::load(file_get_contents($strFile));
	}
  }

  static function ReadDir($strFolder = ""){
	  $arrRet = array();
      if(!$strFolder){
		  $strFolder = Config::$content_folder;
	  }
	  foreach(scandir($strFolder) as $file){
		if(in_array($file, array('.', '..')))
			continue;

		if(preg_match('/^\d.*/', $file) && is_dir($strFolder . '/' . $file)){
			$arrYML = self::ymlContentsFromFolder($strFolder . '/' . $file);
			$arrHashes = array();

			$strCurrentURL = Helpers::relative_root_path(
				Helpers::file_path_to_url($strFolder . '/' . $file));

			if(array_key_exists('content', $arrYML) && !empty($arrYML['content'])){
				$doc = new DOMDocument();
				$doc->loadHTML($arrYML['content']);

				foreach(array('h1', 'h2') as $strTagName){
					$nodeList = $doc->getElementsByTagName($strTagName);
					foreach ($nodeList as $node) {
						if($idAttr = $node->getAttribute('id')){
							$arrHashes[] = array(
								'link'		=> $strCurrentURL . '#' . $idAttr,
								'title'		=> $node->nodeValue,
								'contents'	=> array(
									'title'	=> $node->nodeValue
								)
							);
						}
					}
				}
			}

			$arr = array(
				'title'			=> $file,
				'link'			=> $strCurrentURL,
				'dir'			=> $strFolder . '/' . $file,
				'contents'		=> $arrYML,
				'sub_headings'	=> $arrHashes,
				'children'		=> self::ReadDir($strFolder . '/' . $file)
			);
			$arrRet[] = $arr;
		}
	  }
	  return $arrRet;
  }

}

?>