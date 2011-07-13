<?php
class cueParser{
	function parse($cue=null){
		if(!$cue) return false;
		
		// UTF-ize if necessary
		$cue = preg_replace("/^\xef\xbb\xbf/", '', $cue);
		if(!$encoding = mb_detect_encoding($cue)){
			$cue = mb_convert_encoding($cue, 'UTF-8', 'ISO-8859-1');
		}else{
			$cue = mb_convert_encoding($cue, 'UTF-8', $encoding);
		}
		
		// convert to array
		$cue = str_replace("\r",'',$cue);
		$cue = explode("\n",$cue);
		
		// dump bad lines
		$allowed = array(
			'file',
			'track',
			'performer',
			'title',
			'index'
		);
		foreach($cue as $key => $value){
			$line = preg_replace('/[^a-z]/i','',$value);
			$valid = false;
			foreach($allowed as $x){
				if(preg_match("/^$x/i",$line)) $valid = $x;
			}
			if($valid){
				unset($cue[$key]);
				$cue[$key]['type'] = $valid;
				$cue[$key]['line'] = $value;
			}else{
				unset($cue[$key]);
			}
			unset($line,$valid);
		}
		
		// remove line type identifier
		foreach($cue as $key => $value){
			$line = $value['line'];
			$chars = preg_split('//', $value['type'], -1, PREG_SPLIT_NO_EMPTY);
			foreach($chars as $char){
				$line = preg_replace("/^.*?$char/i",'',$line);
			}
			$cue[$key]['line'] = $line;
			
			unset($line,$chars);
		}
		
		// clean up lines & convert index->frames
		foreach($cue as $key => $value){
			$line = $value['line'];
			switch($value['type']){
				case 'file':
					$line = preg_replace('/^[ |"|\t]*(.*?)[ |"|\t]*$/','$1',$line); // wrappers
					$line = preg_replace('/\w+$/','',$line); // filetype identifier
					$line = preg_replace('/^[ |"|\t]*(.*?)[ |"|\t]*$/','$1',$line); // wrappers
					$line = preg_replace('/\t+/',' ',$line); // tabs
					$line = preg_replace('/ +/',' ',$line); // multi-spaces
					$cue[$key]['line'] = $line;
					break;
				case 'index':
					$line = preg_replace('/^[ |"|\t]*(.*?)[ |"|\t]*$/','$1',$line); // wrappers
					$line = preg_replace('/^\w+/','',$line); // leading identifier
					$line = preg_replace('/[^\d|:]+/','',$line); // everyting non-digit or :
					
					// convert to frames
					$line = explode(':', $line);
					$line = array_reverse($line);
					@$frames = $line[0]; // frames
					@$frames+= $line[1]*75; // seconds
					@$frames+= $line[2]*75*60; // minutes
					@$frames+= $line[3]*75*60*60; // hours (!)
					$cue[$key]['line'] = $frames;
					break;
				default:
					$line = preg_replace('/^[ |"|\t]*(.*?)[ |"|\t]*$/','$1',$line); // wrappers
					$line = preg_replace('/\t+/',' ',$line); // tabs
					$line = preg_replace('/ +/',' ',$line); // multi-spaces
					$cue[$key]['line'] = $line;
					break;
			}
			unset($line,$frames);
		}
		
		// retrieve cuesheet data
		$allowed = array(
			'performer',
			'title',
			'file'
		);
		foreach($cue as $key => $value){
			if($value['type']=='track') break; // reached tracks, stop
			
			if(in_array($value['type'],$allowed)){
				$out['Cuesheet'][$value['type']] = $value['line'];
			}
			unset($cue[$key]);
		}
		
		// retrieve track data
		$allowed = array(
			'performer',
			'title',
			'index'
		);
		foreach($cue as $key => $value){
			if($value['type']=='track'){
				@$i++; // reached new track, increment
			}else{
				if(in_array($value['type'],$allowed)) $out['Track'][$i][$value['type']] = $value['line'];
			}
			unset($cue[$key]);
		}
		$out['Track'] = array_values($out['Track']);
		
		// done, return it
		return $out;
	}
	function compile($cue=null){
		if(!$cue) return false;
		
		// convert frames->index
		if(count(@$cue['Track'])>0)
		foreach($cue['Track'] as $key => $value){
			$i = $value['index'];
			
			$min = floor($i/75/60);
			$sec = floor(($i - ($min*75*60)) /75);
			$frm = floor(($i - ($min*75*60) - ($sec*75)));
			
			$min = str_pad($min, 2, "0", STR_PAD_LEFT);
			$sec = str_pad($sec, 2, "0", STR_PAD_LEFT);
			$frm = str_pad($frm, 2, "0", STR_PAD_LEFT);
			
			$cue['Track'][$key]['index'] = "$min:$sec:$frm";
			unset($i,$min,$sec,$frm);
		}
	
		
		// compile cuesheet
		$out = 'PERFORMER "'.@$cue['Cuesheet']['performer'].'"'.PHP_EOL;
		$out.= 'TITLE "'.@$cue['Cuesheet']['title'].'"'.PHP_EOL;
		$out.= 'FILE "'.@$cue['Cuesheet']['file'].'"';
		if($ext = strtoupper(pathinfo(@$cue['Cuesheet']['file'],PATHINFO_EXTENSION))) $out.= " $ext".PHP_EOL;
		
		if(count(@$cue['Track'])>0)
		foreach($cue['Track'] as $key => $value){
			$key++;
			$out.= '  TRACK '.sprintf("%02d",$key).' AUDIO'.PHP_EOL;
			$out.= '    PERFORMER "'.$value['performer'].'"'.PHP_EOL;
			$out.= '    TITLE "'.$value['title'].'"'.PHP_EOL;
			$out.= '    INDEX 01 '.$value['index'].PHP_EOL;
		}
		
		// done
		return $out;
	}
	function clean($cue=null){
		if(!$cue) return false;
		
		$cue = $this->parse($cue);
		$cue = $this->compile($cue);
		return $cue;
	}
}