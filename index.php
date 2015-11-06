<?php

	// ------------------------------------------------------------------------

	function getPageFromWikipedia( $page)
	{
		$url = "https://de.wikipedia.org/w/api.php?action=query&titles=$page&prop=revisions&rvprop=content&format=json&utf8=";
		$data = file_get_contents( $url, true);
		$json = json_decode( $data, true);

		$pagedata = '';
		foreach( $json['query']['pages'] as $key => $value) {
			$pagedata = $value['revisions'][0]['*'];
		}

		if( 0 === strpos( $pagedata, '#WEITERLEITUNG')) {
			$page = getLinkSite( $pagedata);
			return getPageFromWikipedia( urlencode( $page));
		}

		return $pagedata;
	}

	// ------------------------------------------------------------------------

	function getDistrictlistFromWikipedia( $pagedata)
	{
		$data = substr( $pagedata, strpos( $pagedata, '== Übersicht der Listen der Straßen und Plätze =='));
		$data = substr( $data, strpos( $data, '==') + 2);
		$data = substr( $data, strpos( $data, '==') + 2);
		$data = substr( $data, 0, strpos( $data, '=='));

		$streetVec = explode( PHP_EOL.'|-', $data);
		array_shift( $streetVec);
		array_shift( $streetVec);
		array_pop( $streetVec);

		return $streetVec;
	}

	// ------------------------------------------------------------------------

	function getStreetlistFromWikipedia( $pagedata)
	{
		$pos = strpos( $pagedata, '== Übersicht der Straßen und Plätze ==');
		if( false === $pos) {
			$pos = strpos( $pagedata, '== Übersicht der Straßen ==');
		}
		$data = substr( $pagedata, $pos);
		$data = substr( $data, strpos( $data, '==') + 2);
		$data = substr( $data, strpos( $data, '==') + 2);
		$data = substr( $data, 0, strpos( $data, '=='));

		$streetVec = explode( PHP_EOL.'|-', $data);
		array_shift( $streetVec);
		array_shift( $streetVec);
		array_pop( $streetVec);

		return $streetVec;
	}

	// ------------------------------------------------------------------------

	function explodeWikipediaTableRow( $row)
	{
		$cols = explode( '|', $row);
		array_shift( $cols);

		for( $i = 0; $i < count( $cols); ++$i) {
			$cell = trim( $cols[ $i]);
			$cell_ = preg_replace( '/\s+/', '', $cell);
			$curlyOpen = substr_count( $cell, '{');
			$curlyClose = substr_count( $cell, '}');
			$squareOpen = substr_count( $cell, '[');
			$squareClose = substr_count( $cell, ']');
			$commentOpen = substr_count( $cell, '<!--');
			$commentClose = substr_count( $cell, '-->');
			$refOpen = substr_count( $cell, '<ref');
			$refClose = substr_count( $cell, '/>') + substr_count( $cell, '</ref>');

			if(( $curlyOpen > $curlyClose) || ($squareOpen > $squareClose) || ($commentOpen > $commentClose) || ($refOpen > $refClose)) {
				if(( $i + 1) < count( $cols)) {
					$cols[ $i] = $cell . '|' . trim( $cols[$i+1]);

					unset( $cols[$i+1]);
					$cols = array_values( $cols);
					--$i;

					continue;
				}
			}
			if(( 'align=left' == $cell_) || ('align=center' == $cell_) || ('align=zentriert' == $cell_) || ('align=right' == $cell_) || ('align="right"' == $cell_) || ('style="text-align:right"' == $cell_)) {
				unset( $cols[$i]);
				$cols = array_values( $cols);
				--$i;

				continue;
			}
			if(( 0 === strpos( $cell, "id='")) || (0 === strpos( $cell, "id= '")) || (0 === strpos( $cell, 'id="')) || ((0 === strpos( $cell, 'id=')) && ('*' == substr( $cell, -1)))) {
				unset( $cols[$i]);
				$cols = array_values( $cols);
				--$i;

				continue;
			}
			if( '' === $cell) {
				unset( $cols[$i]);
				$cols = array_values( $cols);
				--$i;

				continue;
			}

			while( false !== strpos( $cell, '<ref')) {
				$pos = strpos( $cell, '<ref');
				$endpos = strpos( $cell, '</ref>', $pos);
				if( false == $endpos) {
					$endpos = strpos( $cell, '/>', $pos) + 2;
				} else {
					$endpos += 6;
				}
				$cell = trim( str_replace( substr( $cell, $pos, $endpos - $pos), '', $cell));
			}

			if( false !== strpos( $cell, '<!--')) {
				$pos = strpos( $cell, '<!--');
				$endpos = strpos( $cell, '-->', $pos) + 3;
				$cell = trim( str_replace( substr( $cell, $pos, $endpos - $pos), '', $cell));
			}

			$cell = trim( strip_tags( $cell));

			if(( 0 === strpos( $cell, '{{Anker')) || (0 === strpos( $cell, '{{0'))) {
				$cell = trim( substr( $cell, strpos( $cell, '}}') + 2));
			}
			if( 0 === strpos( $cell, '{{SortKey')) {
				$sub = substr( $cell, 0, strpos( $cell, '}}') + 2);
				$subArry = explode( '|', substr( $sub, 0, -2));
				if( 0 === strpos( $subArry[2], '[[')) {
					$cell = trim( $subArry[2].'|'.$subArry[3] . substr( $cell, strlen( $sub)));
				} else {
					$cell = trim( $subArry[2] . substr( $cell, strlen( $sub)));
				}
			} else if( 0 === strpos( $cell, '{{SortDate')) {
				$sub = substr( $cell, 0, strpos( $cell, '}}') + 2);
				$cell = trim( explode( '|', substr( $sub, 0, -2))[1] . substr( $cell, strlen( $sub)));
			}

			$cols[ $i] = $cell;
		}

		return $cols;
	}

	// ------------------------------------------------------------------------

	function getIDFromWikidata( $search)
	{
		$url = "https://www.wikidata.org/w/api.php?action=wbsearchentities&format=json&language=de&search=".urlencode($search);
		$data = file_get_contents( $url, true);
		$json = json_decode( $data, true);

		return $json['search'][0]['id'];
	}

	// ------------------------------------------------------------------------

	function getGenderFromWikidata( $id)
	{
		if( 0 == strlen( $id)) {
			return '';
		}
		$url = "https://www.wikidata.org/w/api.php?action=wbgetentities&format=json&props=claims&ids=".urlencode($id);
		$data = file_get_contents( $url, true);
		$json = json_decode( $data, true);
		$value = intval( $json['entities'][$id]['claims']['P21'][0]['mainsnak']['datavalue']['value']['numeric-id']);

		return 0 == $value ? '' : (6581097 == $value ? 'male' : (6581072 == $value ? 'female' : 'unknown ('.$value.')'));
	}

	// ------------------------------------------------------------------------

	function getLinkText( $text)
	{
		$pos = strpos( $text, '[[');
		if( false !== $pos) {
			if( false === strpos( $text, ']]')) {
				$text = substr( $text, $pos + 2);
			} else {
				$text = substr( $text, $pos + 2, strpos( $text, ']]', $pos) - $pos - 2);
			}
			$link = explode( '|', $text);
			if( count( $link) > 1) {
				$text = $link[1];

				if( '{{SortKey' == trim( $text)) {
					$text = substr( $link[3], 0, strpos( $link[3], '}}'));
				}
			}

			return $text;
		}

		return '';
	}

	// ------------------------------------------------------------------------

	function getLinkSite( $text)
	{
		$pos = strpos( $text, '[[');
		if( false !== $pos) {
			if( false === strpos( $text, ']]')) {
				$text = substr( $text, $pos + 2);
			} else {
				$text = substr( $text, $pos + 2, strpos( $text, ']]', $pos) - $pos - 2);
			}
			$link = explode( '|', $text);
			if( count( $link) > 1) {
				$text = $link[0];
			}

			return $text;
		}

		return '';
	}

	// ------------------------------------------------------------------------

	function getStreetName( $line)
	{
		if( count( $line) > 0) {
			$name = trim( explode( '({', $line[0])[0]);
			$name = trim( explode( '([', $name)[0]);
			if( strlen( getLinkText( $name)) > 0) {
				$name = getLinkText( $name);
			}
			if( false !== strpos( $name, '{{')) {
				$pos = strpos( $name, '{{');
				$name = trim( str_replace( substr( $name, $pos, strpos( $name, '}}', $pos) + 2 - $pos), '', $name));
			}
			if( '(*)' == substr( $name, -3)) {
				$name = trim( substr( $name, 0, -3));
			}
			if( '*' == substr( $name, -1)) {
				$name = trim( substr( $name, 0, -1));
			}
			return $name;
		}

		return '';
	}

	// ------------------------------------------------------------------------

	function getStreetCenter( $line)
	{
		$ret = array( 'lat' => 0, 'lng' => 0);
		if( count( $line) > 0) {
			$pos = strpos( $line[0], '{{Coordinate');
			$sub = substr( $line[0], $pos, strpos( $line[0], '}}', $pos));
			$coordinate = explode( '|', $sub);
			foreach( $coordinate as $value) {
				if( 0 === strpos( $value, 'NS=')) {
					$ret['lat'] = trim( substr( $value, 3));
				} else if( 0 === strpos( $value, 'EW=')) {
					$ret['lng'] = trim( substr( $value, 3));
				}
			}
		}

		return $ret;
	}

	// ------------------------------------------------------------------------

	function getStreetDate( $line)
	{
		if( count( $line) > 2) {
			return trim( explode( '{', $line[3])[0]);
		}

		return '';
	}

	// ------------------------------------------------------------------------

	function getStreetOrigin( $line)
	{
		if( count( $line) > 1) {
			return trim( $line[2]);
		}

		return '';
	}

	// ------------------------------------------------------------------------

	function getStreetData( $street, $colCount, $useWikidata)
	{
		$cells = explodeWikipediaTableRow( $street);

		if( count( $cells) != $colCount) {
			if((( 0 === strpos( $cells[0], 'Elbeweg')) && (count( $cells) == 5)) ||
			   (( 0 === strpos( $cells[0], 'Eichgestell')) && (count( $cells) == 5)) ||
			   (( 0 === strpos( $cells[0], 'Magnolienring')) && (count( $cells) == 5)) ||
			   (( 0 === strpos( $cells[0], 'Schönhauser Tor')) && (count( $cells) == 6)) ||
			   (( 0 === strpos( $cells[0], 'Frauenauer Straße')) && (count( $cells) == 5)) ||
			   (( 0 === strpos( $cells[0], '[[Prenzlauer Tor]]')) && (count( $cells) == 6)) ||
			   (( 0 === strpos( $cells[0], 'Kirchdorfer Straße')) && (count( $cells) == 5)) ||
			   (( 0 === strpos( $cells[0], 'Patersdorfer Straße')) && (count( $cells) == 5)) ||
			   (( 0 === strpos( $cells[0], 'Fred-Löwenberg-Platz')) && (count( $cells) == 5)) ||
			   (( 0 === strpos( $cells[0], 'Fromet-und-Moses-Mendelssohn-Platz')) && (count( $cells) == 5))) {
				// length cell is missing
				if( 7 == $colCount) {
					$cells[5] = $cells[4];
				} else {
					$cells[] = $cells[4];
				}
				$cells[4] = $cells[3];
				$cells[3] = $cells[2];
				$cells[2] = $cells[1];
				$cells[1] = '0';
			} else if((( 0 === strpos( $cells[0], 'Platz C')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Platz E')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Straße 106')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Straße 206')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Straße 210')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Straße 245')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Straße 246')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Straße 250')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Straße 251')) && (count( $cells) == 5))) {
				// origin cell is empty
				$cells[] = $cells[4];
				$cells[4] = $cells[3];
				$cells[3] = $cells[2];
				$cells[2] = '';
			} else if((( 0 === strpos( $cells[0], 'Eosanderplatz')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Tiergartenufer')) && (count( $cells) == 5))) {
				// date cell is empty
				$cells[] = $cells[4];
				$cells[4] = $cells[3];
				$cells[3] = '';
			} else if((( 0 === strpos( $cells[0], 'Jagowstraße')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Welzower Steig')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Tiefenseer Straße')) && (count( $cells) == 5))) {
				// description cell is missing
				$cells[] = $cells[4];
				$cells[4] = '';
			} else if((( 0 === strpos( $cells[0], '[[Garbátyplatz]]')) && (count( $cells) == 7))) {
				// description cell contain the | char
				$cells[4] += '|' . $cells[5];
				unset( $cells[5]);
				$cells = array_values( $cells);
			} else if((( 0 === strpos( $cells[0], 'Minna-Flake-Platz')) && (count( $cells) == 4))) {
				$cells[] = '';
				$cells[4] = $cells[2];
				$cells[3] = $cells[1];
				$cells[2] = '';
				$cells[1] = '0';
			} else if((( 0 === strpos( $cells[0], 'Lennéplatz')) && (count( $cells) == 4)) ||
			          (( 0 === strpos( $cells[0], 'Prinzengasse')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Teichrohrplatz')) && (count( $cells) == 4)) ||
			          (( 0 === strpos( $cells[0], 'Stadtbahnbogen')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Rosa-Parks-Platz')) && (count( $cells) == 5)) ||
			          (( 0 === strpos( $cells[0], 'Lilienthalstraße')) && (count( $cells) == 8)) ||
			          (( 0 === strpos( $cells[0], 'Bernburger Treppe')) && (count( $cells) == 3)) ||
			          (( 0 === strpos( $cells[0], 'Rummelsburger Straße')) && (count( $cells) == 4)) ||
			          (( 0 === strpos( $cells[0], '[[#KGA|Siedlersparte Wuhlesee]]')) && (count( $cells) == 4))) {
				return array();
			} else {
				foreach( $cells as $cell) {
					echo '- '.htmlspecialchars($cell).'<br>';
				}
				exit( "The page has an error in line '".getStreetName( $cells)."'");
			}
		} else if(( false !== strpos( $cells[0], 'Olmweg (*)  XXXXXXXXX')) ||
		          ( 0 === strpos( $cells[0], 'Dianasteg'))) {
			return array();
		}

		$data = array();
		$data['title'] = getStreetName( $cells);
		$data['lat'] = getStreetCenter( $cells)['lat'];
		$data['lng'] = getStreetCenter( $cells)['lng'];
		$data['date'] = getStreetDate( $cells);
		$data['link'] = getLinkSite( getStreetOrigin( $cells));

		if( $useWikidata) {
			$data['wikidata'] = getIDFromWikidata( $data['link']);

			if(( 0 == strlen( $data['wikidata'])) && ($data['link'] != getLinkText( getStreetOrigin( $cells)))) {
				$data['wikidata'] = getIDFromWikidata( getLinkText( getStreetOrigin( $cells)));
				if( 0 == strlen( $data['wikidata'])) {
					$data['wikidata'] = '';
				}
			}
			$data['gender'] = getGenderFromWikidata( $data['wikidata']);
		}

		return $data;
	}

	// ------------------------------------------------------------------------

	function getCityData()
	{
		$page = 'Stra%C3%9Fen_und_Pl%C3%A4tze_in_Berlin';
		$pagedata = getPageFromWikipedia( $page);
		$districts = getDistrictlistFromWikipedia( $pagedata);
		$data = array();

		for( $id = 0; $id < (count( $districts) - 1); ++$id) {
			$cells = explodeWikipediaTableRow( $districts[ $id]);
			$link = getLinkSite( $cells[0]);

			$item = array();
			$item['id'] = $id;
			$item['district'] = substr( $link, strpos( $link, 'Berlin'));
			$data[] = $item;
		}

		return $data;
	}

	// ------------------------------------------------------------------------

	function getDistrictData( $name, $useWikidata)
	{
		$pagedata = getPageFromWikipedia( urlencode( $name));
		$streets = getStreetlistFromWikipedia( $pagedata);
		$data = array();
		$colCount = 6;

		if( false !== strpos( $name, 'Berlin-Prenzlauer Berg')) {
			$colCount = 7;
		}

		foreach( $streets as $street) {
			if(( strlen( trim( $street)) > 0) && ('-->' != trim($street)) && ('->' != trim($street))) {
				$item = getStreetData( $street, $colCount, $useWikidata);
				if( count( $item) > 0) {
					$data[] = $item;
				}
			}
		}

		return $data;
	}

	// ------------------------------------------------------------------------

	$fail = true;
	$show = '';
	$id = '';
	$details = '';
	if( isset( $_GET[ 'show'])) {
		$show = $_GET[ 'show'];
	}
	if( isset( $_GET[ 'id'])) {
		$id = $_GET[ 'id'];
	}
	if( isset( $_GET[ 'details'])) {
		$details = $_GET[ 'details'];
	}

	if( 'districts' == $show) {
		$data = getCityData();
		$fail = false;

		echo json_encode( $data, JSON_UNESCAPED_UNICODE);
	} else if(( 'streets' == $show) && ('' != $id)) {
		$page = 'Stra%C3%9Fen_und_Pl%C3%A4tze_in_Berlin';
		$pagedata = getPageFromWikipedia( $page);
		$districts = getDistrictlistFromWikipedia( $pagedata);
		$district = intval( $id);

		if(( $district >= 0) && ($district < (count( $districts) - 1))) {
			$cells = explodeWikipediaTableRow( $districts[$district]);
			$link = getLinkSite( $cells[0]);
			$useWikidata = 'on' == $details;
			$data = getDistrictData( $link, $useWikidata);
			$fail = false;

			echo json_encode( $data, JSON_UNESCAPED_UNICODE);
		}
	}

	if( $fail) {
		echo '<h1>How to use this API</h1>';

		echo '<h2>Show all districts of Berlin</h2>';
		echo '<table border=1>';
		echo '<tr><td>Parameter</td><td>Type</td><td>Value</td><td>Description</td></tr>';
		echo '<tr><td>show</td><td>string</td><td>districts</td><td>Get a JSON object of all 95 districts of Berlin.<br><br>"id" is the index number of the district<br>"district" is the readable name of the district</td></tr>';
		echo '</table><br>';
		echo 'Sample: <a href="?show=districts">index.php?show=districts</a><br>';
		echo 'Result: [{"id":0,"district":"Berlin-Adlershof"}, ...]<br>';

		echo '<h2>Show all streets of a district</h2>';
		echo '<table border=1>';
		echo '<tr><td>Parameter</td><td>Type</td><td>Value</td><td>Description</td></tr>';
		echo '<tr><td>show</td><td>string</td><td>streets</td><td>Get a JSON object of all streets in a district of Berlin.<br><br>"title" is the name of the street<br>"gps" is the middle GPS position of the street in an {lat,lng} object<br>"date" is the date when the street has been named<br>"link" is the wikipedia link for more information about the name of the street<br>"wikidata" is the id of an existing object in Wikidata (if present)<br>"gender" is the gender of the name by which the street has been named (if any)</td></tr>';
		echo '<tr><td>id</td><td>integer</td><td> </td><td>The index number of the district</td></tr>';
		echo '<tr><td>details</td><td>string</td><td> </td><td>This parameter is optional.<br>If the value is set to "on", more information will be gathered from Wikidata. Only if "on" is chosen, the fields "wikidata" and "gender" will be present in the JSON result.<br><br>Note: The response time will be significantly increased!</td></tr>';
		echo '</table><br>';
		echo 'Sample: <a href="?show=streets&id=52">index.php?show=streets&id=52</a><br>';
		echo 'Result: [{"title":"Blankenburger Pflasterweg","gps":{"lat":"52.583218","lng":"13.481958"},"date":"nach 1882","link":"Berlin-Blankenburg"}, ...]<br>';
		echo '<br>';
		echo 'Sample: <a href="?show=streets&id=52&details=on">index.php?show=streets&id=52&details=on</a><br>';
		echo 'Result: [{"title":"Blankenburger Pflasterweg","gps":{"lat":"52.583218","lng":"13.481958"},"date":"nach 1882","link":"Berlin-Blankenburg","wikidata":"Q693582","gender":""}, ...]<br>';
	}

	// ------------------------------------------------------------------------

?>
