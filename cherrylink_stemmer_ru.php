<?php
/*
 * CherryLink Plugin
 */

// Disable direct access

namespace Stem;
defined( 'ABSPATH' ) || exit;

class LinguaStemRu
{
	var $VERSION = "0.02";
	var $Stem_Caching = 1;
    var $Stem_Cache = array();
    var $Stem_Enabled = false;

	var $VOWEL = '/аеиоуыэюя/';
	var $PERFECTIVEGROUND = '/((ив|ивши|ившись|ыв|ывши|ывшись)|((?<=[ая])(в|вши|вшись)))$/';
	var $REFLEXIVE = '/(с[яь])$/';
	var $ADJECTIVE = '/(ее|ие|ые|ое|ими|ыми|ей|ий|ый|ой|ем|им|ым|ом|его|ого|ему|ому|их|ых|ую|юю|ая|яя|ою|ею)$/';
	var $PARTICIPLE = '/((ивш|ывш|ующ)|((?<=[ая])(ем|нн|вш|ющ|щ)))$/';
	var $VERB = '/((ила|ыла|ена|ейте|уйте|ите|или|ыли|ей|уй|ил|ыл|им|ым|ен|ило|ыло|ено|ят|ует|уют|ит|ыт|ены|ить|ыть|ишь|ую|ю)|((?<=[ая])(ла|на|ете|йте|ли|й|л|ем|н|ло|но|ет|ют|ны|ть|ешь|нно)))$/';
	var $NOUN = '/(а|ев|ов|ие|ье|е|ьё|иями|ями|ами|еи|ии|и|ией|ей|ой|ий|й|иям|ям|ием|ем|ам|ом|о|у|ах|иях|ях|ы|ь|ию|ью|ю|ия|ья|я)$/';
	var $RVRE = '/^(.*?[аеиоуыэюя])(.*)$/';
    var $DERIVATIONAL = '/[^аеиоуыэюя][аеиоуыэюя]+[^аеиоуыэюя]+[аеиоуыэюя].*(?<=о)сть?$/';
    var $REGEX_SUPERLATIVE = '/.+?(ейш|ейше)$/';
    var $LEGIT = '/[а-я]/';

	function __construct() {
		mb_internal_encoding('UTF-8');
	}

	function s(&$s, $re, $to)
	{
		$orig = $s;
		$s = preg_replace($re, $to, $s);
		return $orig !== $s;
	}

	function m($s, $re)
	{
		return preg_match($re, $s);
    }
    
    // function stem_word($word) {
    //     return $word;
    // }

	function stem_word($word)
	{
		$word = mb_strtolower($word);
        $word = str_replace('ё', 'е', $word); // замена ё на е, что бы учитывалась как одна и та же буква
        
        // Этот стеммер портит символы, поэтому такие не обрабатываем. Зато работает быстро :)
        // if ($this->m($word, $this->REGEX_SUPERLATIVE)) {
        //     return $word;
        // }

		# Check against cache of stemmed words
		if ($this->Stem_Caching && isset($this->Stem_Cache[$word])) {
			return $this->Stem_Cache[$word];
		}
		$stem = $word;
		do {
			if (!preg_match($this->RVRE, $word, $p)) break;
			$start = $p[1];
			$RV = $p[2];
			if (!$RV) break;

			# Step 1
			if (!$this->s($RV, $this->PERFECTIVEGROUND, '')) {
				$this->s($RV, $this->REFLEXIVE, '');

				if ($this->s($RV, $this->ADJECTIVE, '')) {
					$this->s($RV, $this->PARTICIPLE, '');
				} else {
					if (!$this->s($RV, $this->VERB, ''))
						$this->s($RV, $this->NOUN, '');
				}
			}

			# Step 2
			$this->s($RV, '/и$/', '');

			# Step 3
			if ($this->m($RV, $this->DERIVATIONAL))
				$this->s($RV, '/ость?$/', '');

			# Step 4
			if (!$this->s($RV, '/ь$/', '')) {
				$this->s($RV, '/ейше?/', '');
				$this->s($RV, '/нн$/', 'н');
			}

			$stem = $start.$RV;
		} while(false);

		if (!$this->m($stem, $this->LEGIT)) {
		    return $word;
        }
		if (!iconv("UTF-8", "UTF-8//IGNORE", $stem)) {
		    return $word;
        }

		if ($this->Stem_Caching) $this->Stem_Cache[$word] = $stem;
		return $stem;
	}


	/**
	 * Стэмит все русские слова в тексте, оставляя пробелы и прочие знаки препинания на месте.
	 * @param $text
	 * @return string
	 */
	function stem_text($text)
	{
		$separators_arr= array('?',' ', '.', ',', ';','!','"','\'','`',"\r","\n","\t");
		$pos = 0;
		while($pos<mb_strlen($text)){
			$min_new_pos = mb_strlen($text);
			foreach ($separators_arr as $sep) {
				$newpos_candidate = mb_strpos($text, $sep, $pos);
				if($newpos_candidate!==FALSE) {
					$min_new_pos = ($newpos_candidate < $min_new_pos) ? $newpos_candidate : $min_new_pos;
				}
			}
			$newpos = $min_new_pos;
			$word_part = mb_substr($text, $pos, $newpos-$pos);
			$word = preg_replace("/[^АБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯабвгдеёжзийклмнопрстуфхцчшщъыьэюя\x{2010}-]/u","",$word_part);
			if($word == ''){
				$pos = $newpos+1;
			}else{
				$word_stemmed = $this->stem_word($word);
				$word_stemmed_part = str_replace($word,$word_stemmed,$word_part);

				$text = mb_substr($text,0,$pos) . $word_stemmed_part . mb_substr($text, $newpos);

				$pos = $newpos - (mb_strlen($word)-mb_strlen($word_stemmed));
			}
		}
		return $text;
	}

	function stem_caching($parm_ref)
	{
		$caching_level = @$parm_ref['-level'];
		if ($caching_level) {
			if (!$this->m($caching_level, '/^[012]$/')) {
				die(__CLASS__ . "::stem_caching() - Legal values are '0','1' or '2'. '$caching_level' is not a legal value");
			}
			$this->Stem_Caching = $caching_level;
		}
		return $this->Stem_Caching;
	}

	function clear_stem_cache()
	{
		$this->Stem_Cache = array();
    }
    
    function enable_stemmer($enable) {
        $this->Stem_Enabled = $enable;
    }
}