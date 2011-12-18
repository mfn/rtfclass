<?
	// use tabstop=4 

	/*
		Rich Text Format - Parsing Class

		(c) 2000 Markus Fischer <mfischer@josefine.ben.tuwien.ac.at>
		License: GPLv2

		Documentation: http://msdn.microsoft.com
			( Search in the Library for specification)
	*/

	class rtfState {
		var $bold;
		var $italic;
		var $underlined;
	}

	class rtf {
		var $rtf;		// rtf core stream
		var $len;		// length in characters of the stream
		var $err = array();		// array of error message, no entities on no error

		var $wantXML;	// convert to ...

		var $out;		// output data stream (depends on which $wantXXXXX is set to true

		/* keywords which don't follw the specification (used by Word '97 - 2000) */
		var $control_exception = array(
			"clFitText",
			"clftsWidth(-?[0-9]+)?",
			"clNoWrap(-?[0-9]+)?",
			"clwWidth(-?[0-9]+)?",
			"tdfrmtxtBottom(-?[0-9]+)?",
			"tdfrmtxtLeft(-?[0-9]+)?",
			"tdfrmtxtRight(-?[0-9]+)?",
			"tdfrmtxtTop(-?[0-9]+)?",
			"trftsWidthA(-?[0-9]+)?",
			"trftsWidthB(-?[0-9]+)?",
			"trftsWidth(-?[0-9]+)?",
			"trwWithA(-?[0-9]+)?",
			"trwWithB(-?[0-9]+)?",
			"trwWith(-?[0-9]+)?",
			"spectspecifygen(-?[0-9]+)?"
			);

		function rtf( $data) {
			$this->len = strlen( $data);
			$this->rtf = $data;

			$this->wantXML = false;
			$this->out = "";
			$this->text = "";

			if( $this->len == 0)
				array_push( $this->err, "No data in stream found");
		}

		function output( $typ) {
			switch( $typ) {
				case "xml": $this->wantXML = true; break;
				default: break;
			}
		}

		function flushControl( $cword) {
			if( ereg( "^([A-Za-z]+)(-?[0-9]*) ?$", $cword, $match)) {
				if( $this->wantXML) {
					$this->out.="<control word=\"".$match[1]."\"";
					if( strlen( $match[2]) > 0)
						$this->out.=" param=\"".$match[2]."\"";
					$this->out.="/>";
				}
			}
		}

		function flushComment( $comment) {
			if( $this->wantXML) {
				$this->out.="<!-- ".$comment." -->";
			}
		}

		function flushGroup( $state) {
			if( $state == "open") {
				if( $this->wantXML)
					$this->out.="<group>";
			}
			if( $state == "close") {
				if( $this->wantXML)
					$this->out.="</group>";
			}
		}

		function flushHead() {
			if( $this->wantXML)
				$this->out.="<rtf>";
		}

		function flushBottom() {
			if( $this->wantXML)
				$this->out.="</rtf>";
		}

		function flushQueue( &$text) {
			if( strlen( $text)) {
				if( $this->wantXML)
					$this->out.= "<plain>".$text."</plain>";
				$text = "";
			}
		}

		function flushSpecial( $special) {
			if( strlen( $special) == 2) {
				if( $this->wantXML)
					$this->out .= "<special value=\"".$special."\"/>";
			}
		}

		function parse() {

			$i = 0;
			$cw = false;	// flag if control word is currently parsed
			$cfirst = false;// first control character ?
			$cword = "";	// last or current control word ( depends on $cw)

			$queue = "";		// plain text data found during parsing

			$this->flushHead();

			while( $i < $this->len) {
				switch( $this->rtf[$i]) {
					case "{":	if( $cw) {
									$this->flushControl( $cword);
									$cw = false; $cfirst = false;
								} else 
									$this->flushQueue( $queue);

								$this->flushGroup( "open");
								break;
					case "}":	if( $cw) {
									$this->flushControl( $cword);
									$cw = false; $cfirst = false;
								} else
									$this->flushQueue( $queue);

								$this->flushGroup( "close");
								break;
					case "\\":	if( $cfirst) {	// catches '\\' 
									$this->flushComment( "true, ".$i);
									$queue .= '\\';
									$cfirst = false;
									$cw = false;
									break;
								}
								if( $cw) {
									$this->flushControl( $cword);
								} else 
									$this->flushQueue( $queue);
								$cw = true;
								$cfirst = true;
								$cword = "";
								break;
					default:	
								if( (ord( $this->rtf[$i]) == 10) || (ord($this->rtf[$i]) == 13)) break; // eat line breaks
								if( $cw) {	// active control word ?
									/*
										Watch the RE: there's an optional space at the end which IS part of
										the control word (but actually its ignored by flushControl)
									*/
									if( ereg( "^[a-zA-Z0-9-] ?$", $this->rtf[$i])) { // continue parsing
										$cword .= $this->rtf[$i];
										$cfirst = false;
									} else {
										/*
											Control word could be a 'control symbol', like \~ or \* etc.
										*/
										$specialmatch = false;
										if( $cfirst) {
											if( $this->rtf[$i] == '\'') { // expect to get some special chars
												$this->flushQueue( $queue);
												$this->flushSpecial( $this->rtf[$i+1].$this->rtf[$i+2]);
												$i+=2;
												$specialmatch = true;
												$cw = false; $cfirst = false; $cw = "";
											} else 
											if( ereg( "^[{}\*]$", $this->rtf[$i])) {
												$this->flushComment( "control symbols not yet handled");
												$specialmatch = true;
												$queue .= $this->rtf[$i];
											}
											$cfirst = false;
										} 
										if( ! $specialmatch) {
											$this->flushControl( $cword);
											$cw = false; $cfirst = false;
											/*
												The current character is a delimeter, but is NOT
												part of the control word so we hop one step back
												in the stream
											*/
											$i--;
										}
									}
								} else {
									$queue .= $this->rtf[$i];
								}
									
				}
				$i++;
			}
			$this->flushQueue( $queue);
			$this->flushBottom();
		}
	}
?>
