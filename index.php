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

		return $pagedata;
	}

	// ------------------------------------------------------------------------

	function getDistrictlistFromWikipedia( $pagedata)
	{
		$data = substr( $pagedata, strpos( $pagedata, '== Übersicht der Listen der Straßen und Plätze =='));
		$data = substr( $data, strpos( $data, '==') + 2);
		$data = substr( $data, strpos( $data, '==') + 2);
		$data = substr( $data, 0, strpos( $data, '=='));

		$streetVec = explode( '|-', $data);
		array_shift( $streetVec);
		array_shift( $streetVec);
		array_pop( $streetVec);

		return $streetVec;
	}

	// ------------------------------------------------------------------------

	function getStreetlistFromWikipedia( $pagedata)
	{
		$data = substr( $pagedata, strpos( $pagedata, '== Übersicht der Straßen und Plätze =='));
		$data = substr( $data, strpos( $data, '==') + 2);
		$data = substr( $data, strpos( $data, '==') + 2);
		$data = substr( $data, 0, strpos( $data, '=='));

		$streetVec = explode( '|-', $data);
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
			$curlyOpen = substr_count( $cell, '{');
			$curlyClose = substr_count( $cell, '}');
			$squareOpen = substr_count( $cell, '[');
			$squareClose = substr_count( $cell, ']');
			$commentOpen = substr_count( $cell, '<!--');
			$commentClose = substr_count( $cell, '-->');

			if(( $curlyOpen > $curlyClose) || ($squareOpen > $squareClose) || ($commentOpen > $commentClose)) {
				if(( $i + 1) < count( $cols)) {
					$cols[ $i] = $cell . '|' . trim( $cols[$i+1]);

					unset( $cols[$i+1]);
					$cols = array_values( $cols);
					--$i;

					continue;
				}
			}
			if(( 'align=left' == $cell) || ('align=center' == $cell) || ('align=right' == $cell)) {
				unset( $cols[$i]);
				$cols = array_values( $cols);
				--$i;

				continue;
			}
			if(( 0 === strpos( $cell, "id='")) || (0 === strpos( $cell, 'id="'))) {
				unset( $cols[$i]);
				$cols = array_values( $cols);
				--$i;

				continue;
			}
			if( '' === $cols[ $i]) {
				unset( $cols[$i]);
				$cols = array_values( $cols);
				--$i;

				continue;
			}

			$cell = trim( strip_tags( $cell));

			if(( 0 === strpos( $cell, '{{Anker')) || (0 === strpos( $cell, '{{0'))) {
				$cell = trim( substr( $cell, strpos( $cell, '}}') + 2));
			}
			if( 0 === strpos( $cell, '{{SortKey')) {
				$sub = substr( $cell, 0, strpos( $cell, '}}') + 2);
				$cell = trim( explode( '|', substr( $sub, 0, -2))[2] . substr( $cell, strlen( $sub)));
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
			if( strlen( getLinkText( $name)) > 0) {
				$name = getLinkText( $name);
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

	function getStreetData( $street, $useWikidata)
	{
		$cells = explodeWikipediaTableRow( $street);

		if( count( $cells) != 6) {
			foreach( $cells as $cell) {
				echo '- '.$cell.'<br>';
			}
			exit( "The page <a href='https://de.wikipedia.org/wiki/$page'>$page</a> has an error in line '".getStreetName( $cells)."'");
		}

		$data = array();
		$data['title'] = getStreetName( $cells);
		$data['gps'] = getStreetCenter( $cells);
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

	function getDistrictData( $name)
	{
		$pagedata = getPageFromWikipedia( urlencode( $name));
		$streets = getStreetlistFromWikipedia( $pagedata);
		$data = array();

		foreach( $streets as $street) {
			$item = getStreetData( $street, false);
			$data[] = $item;

			echo $item['title'].'<br>';
		}

		return $data;
	}

	// ------------------------------------------------------------------------

	$page = 'Stra%C3%9Fen_und_Pl%C3%A4tze_in_Berlin';
	$pagedata = getPageFromWikipedia( $page);
	$districts = getDistrictlistFromWikipedia( $pagedata);
	foreach( $districts as $district) {
		$cells = explodeWikipediaTableRow( $district);
		$link = getLinkSite( $cells[0]);
		echo $link.'<br>';

		$data = getDistrictData( $link);
		echo json_encode( $data, JSON_UNESCAPED_UNICODE);

		break;
	}

//	$data = getDistrictData( 'Liste_der_Straßen_und_Plätze_in_Berlin-Friedrichshain');
//	$data = getDistrictData( 'Liste_der_Straßen_und_Plätze_in_Berlin-Lichtenberg');
//
//	echo json_encode( $data, JSON_UNESCAPED_UNICODE);
?>
