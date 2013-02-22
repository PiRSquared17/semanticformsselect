<?php
if( !defined( 'MEDIAWIKI' ) ) {
	die("This file is an extension to the MediaWiki software and cannot be used standalone.\n");
}
 $wgExtensionCredits[defined( 'SEMANTIC_EXTENSION_TYPE' ) ? 'semantic' : 'specialpage'][] = array(
       'path' => __FILE__,
       'name' => 'SemanticFormsSelect',
       'author' =>array( '[http://www.mediawiki.org/wiki/User:Jasonzhang Jasonzhang]'),
       'url' => 'https://www.mediawiki.org/wiki/Extension:SemanticFormsSelect', 
       'description' => 'Generate a select field in Semantic Form which values are from query',
       'version'  => 1.1,
       );
$wgAjaxExportList[] = "QueryExecution";
$wgAjaxExportList[] = "FunctionExecution";
$wgExtensionFunctions[] = "SFSelect_formSetup";

$wgSF_SelectDir = dirname(__FILE__) ;
$wgSF_SelectScriptPath  = $wgScriptPath . '/extensions/'.basename($wgSF_SelectDir);
$wgScriptSelectCount=0;

$wgSF_Select_debug=0;

if ( defined( 'MW_SUPPORTS_RESOURCE_MODULES' ) ) 
{
	$wgResourceModules['ext.sf_select.scriptselect'] = array(
		'localBasePath' => $wgSF_SelectDir,
		'remoteExtPath' => 'SemanticFormsSelect',
		 'scripts' => array( 'scriptSelect.js' ),
		 'dependencies' => array('ext.semanticforms.main')
	);
}


function SFSelect_formSetup() {
	global $sfgFormPrinter, $wgOut;
	$sfgFormPrinter->setInputTypeHook('SF_Select','SF_Select',array());
}

function QueryExecution($query)
{
	/*@var wgParser Parser */
	global $wgParser, $wgSF_Select_debug;
	
	//wfDebugLog("EPA","query: ".$query);
	$query=str_replace("&lt;", "<", $query);
	$query=str_replace("&gt;", ">", $query);
	$params=explode(";", $query);

	$wgParser->setTitle(Title::newFromText( 'NO TITLE' ));
	$wgParser->mOptions=new ParserOptions();
	$wgParser->firstCallInit();

	
	$f=str_replace(";", "|", $params[0]);
	$params[0]=$wgParser->replaceVariables($f);
	if ($wgSF_Select_debug)
	{
		error_log(implode("|", $params));
	}
	
	$result=SMWQueryProcessor::getResultFromFunctionParams($params,SMW_OUTPUT_WIKI);

	$values=explode(",", $result);
	$values=array_map("trim", $values);
	$values=array_unique($values);
	sort($values);
	array_unshift($values, "");
	$ret=array( "values"=>$values);
	$json= json_encode($ret);
	return $json;
}


function FunctionExecution($f)
{
	global $wgParser, $wgSF_Select_debug;
	
	$wgParser->firstCallInit();
	$wgParser->setTitle(Title::newFromText( 'NO TITLE' ));
	$wgParser->mOptions=new ParserOptions();
	
	$f=str_replace(";", "|", $f);
	if ($wgSF_Select_debug)
	{
		error_log($f);
	}
	$values=$wgParser->replaceVariables($f);
	$values=explode(",", $values);
	$values=array_map("trim", $values);
	$values=array_unique($values);
	sort($values);
	array_unshift($values, "");
	$ret=array("values"=>$values);
	return json_encode($ret);
}




function SF_Select ($cur_value, $input_name, $is_mandatory, $is_disabled, $other_args)
{
	global $wgOut, $wgScriptPath,$wgSF_SelectDir, $wgScriptSelectCount, $sfgFieldNum, $wgUser, $wgParser,$wgSF_SelectScriptPath;
	$selectField=array();
	$values=null;
	$staticvalue=false;
	if (array_key_exists("query", $other_args))
	{
		$query=$other_args["query"];
		$query=str_replace("~", "=", $query);
		$query=str_replace("(", "[", $query);
		$query=str_replace(")", "]", $query);
		
		$selectField["query"]=$query;
		if (strpos($query, '@@@@')===false)
		{
			$params=explode(";", $query);
			$params[0]=$wgParser->replaceVariables($params[0]);
			$values=SMWQueryProcessor::getResultFromFunctionParams($params,SMW_OUTPUT_WIKI);
			$staticvalue=true;
		}
		
	} else if (array_key_exists("function", $other_args))
	{
		$query=$other_args["function"];
		$query='{{#'.$query.'}}';
		$query=str_replace("~", "=", $query);
		$query=str_replace("(", "[", $query);
		$query=str_replace(")", "]", $query);
		$selectField["function"]=$query;
		if (strpos($query, '@@@@')===false)
		{
			$f=str_replace(";", "|", $query);
			$values=$wgParser->replaceVariables($f);
			$staticvalue=true;
		}
	}
	$script="";
	if ($staticvalue)
	{
		$values=explode(",", $values);
		$values=array_map("trim", $values);
		$values=array_unique($values);
	} else
	{
		if ($wgScriptSelectCount==0)
		{
			if ( class_exists( 'ResourceLoader' ) ) 
			{
				$wgOut->addModules('ext.sf_select.scriptselect');	
			} else
			{
				$wgOut->addScript( "<script type='text/javascript' src='{$wgSF_SelectScriptPath}/scriptSelect.js'></script>" );
			}
			$script.="var SFSelect_fobjs=[]; var selectobj=null;\n";
		}
		$wgScriptSelectCount++;
		if (array_key_exists("part_of_multiple", $other_args))
		{
			$selectField["ismultiple"]="true";
		} else
		{
			$selectField["ismultiple"]="false";
		}
		$index=strpos($input_name, "[");
		$selectField["template"]=substr($input_name, 0, $index);
		//Does hit work for multiple template?
		$index=strrpos($input_name, "[");
		$selectField["field"]=substr($input_name, $index+1, strlen($input_name)-$index-2);
		$valueField=array();
		if (array_key_exists("sametemplate", $other_args))
		{
			$valueField["template"]=$selectField["template"];
		} else
		{
			$valueField["template"]=$other_args["template"];
		}
		$valueField["field"]=$other_args["field"];
		$valuerm='false';
		if (array_key_exists('rmdiv', $other_args))
		{
			$valuerm='true';
		}
	
		$selectScript=<<<EOF
	    selectobj={
		valuetemplate:"{$valueField['template']}",
		valuefield:"{$valueField['field']}",
		selectrm:$valuerm,
		
		selecttemplate:"{$selectField['template']}",
		selectfield:"{$selectField['field']}",
		selectismultiple:{$selectField['ismultiple']},
		
EOF;
		if (array_key_exists("query", $selectField))
		{
			$selectScript.="selectquery:\"{$selectField['query']}\"\n};\n";
		} else
		{
			$selectScript.="selectfunction:\"{$selectField['function']}\"\n};\n";
		}

		$selectScript.=<<<EOF
SFSelect_fobjs.push(selectobj);
EOF;

		$script.=$selectScript;
	}
	$extraatt="";
	$is_list=false;
	if (array_key_exists('is_list', $other_args) && $other_args['is_list']==true)
	{
		$is_list=true;
	}
	if ($is_list)
	{
		$extraatt=' multiple="multiple" ';
	}
	if(array_key_exists("size", $other_args))
	{
		$extraatt.=" size=\"{$other_args['size']}\"";
	}
	$classes=array();
	if($is_mandatory)
	{
		$classes[]="mandatoryField";
	}
	if (array_key_exists("class", $other_args))
	{
		$classes[]=$other_args['class'];
	} 
	if ($classes)
	{
		$cstr=implode(" ",$classes);
		$extraatt.=" class=\"$cstr\"";
	}
	$inname=$input_name;
	if ($is_list)
	{
		$inname.='[]';
	} 
	$spanextra=$is_mandatory?'mandatoryFieldSpan':'';
	$ret="<span class=\"inputSpan $spanextra\"><select name='$inname' id='input_$sfgFieldNum' $extraatt>";
	$curvalues=null;
	if ($cur_value)
	{
		if ($cur_value==='current user')
		{
			$cur_value=$wgUser->getName();
		}
		if (is_array($cur_value) )
		{
			$curvalues=$cur_value;
		} else
		{
			$curvalues=array_map("trim", explode(",", $cur_value));
		}
		
	} else
	{
		$curvalues=array();
	}
	
		//TODO handle empty value case.
	$ret.="<option></option>";
	foreach ($curvalues as $cur)
	{
		$ret.="<option selected='selected'>$cur</option>";	
	}
	if ($staticvalue)
	{
		foreach($values as $val)
		{
			if(!in_array($val, $curvalues))
			{
				$ret.="<option>$val</option>";	
			}
		}
	}
	$ret.="</select></span>";
	$ret.="<span id=\"info_$sfgFieldNum\" class=\"errorMessage\"></span>";
	if ($other_args["is_list"])
	{
		$hiddenname=$input_name.'[is_list]';
		$ret.="<input type='hidden' name='$hiddenname' value='1' />";
	}
	

	if (!$staticvalue)
	{
		$wgOut->addInlineScript("$script\n");
	}
	//return array($ret, $script);
	return $ret;
}



?>