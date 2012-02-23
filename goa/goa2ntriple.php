<?php
###############################################################################
#Copyright (C) 2012 Jose Cruz-Toledo
#
#Permission is hereby granted, free of charge, to any person obtaining a copy of
#this software and associated documentation files (the "Software"), to deal in
#the Software without restriction, including without limitation the rights to
#use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies
#of the Software, and to permit persons to whom the Software is furnished to do
#so, subject to the following conditions:
#
#The above copyright notice and this permission notice shall be included in all
#copies or substantial portions of the Software.
#
#THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
#IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
#FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
#AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
#LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
#OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
#SOFTWARE.
###############################################################################

$path = "/home/jose/tmp/gene_association.goa_dog.gz";

parse_goa_file($path);


function parse_goa_file($path){
	$fh = gzopen($path,'r') or die("Cannot open $path !\n");
	if($fh){
		while(!gzeof($fh)){
			$aLine = gzgets($fh, 4096);
			$parsedLine = parse_goa_file_line($aLine);
			if($parsedLine != null){
				//get the Go id
				$db_id = $parsedLine[0];
				$db_object_id = $parsedLine[1];
				$db_object_symbol = $parsedLine[2];
				$qualifier = $parsedLine[3];
				$go_id = $parsedLine[4];
				$db_references = getDbReferences($parsedLine[5]);
				$evidence_code = getEvidenceCodeLabelArr($parsedLine[6]);
				$aspectLabel = getAspectLabel($parsedLine[8]);
				$geneProduct = $parsedLine[9];
				$geneSynonyms = getGeneSynonyms($parsedLine[10]);
				$taxid = getTaxid($parsedLine[12]);
				$date = $parsedLine[13];	
				$assignedBy = $parsedLine[14];
				
				$entryUri = getdbURI($db_id,$db_object_id);
				$buf ="";
				
				$buf .= "<$entryUri> <http://bio2rdf.org/goa_vocabulary:has_alternative_symbol> \"$db_object_symbol\" .\n";
				if(strlen($qualifier)){
					$buf .= "<$entryUri> <http://bio2rdf.org/goa_vocabulary:has_qualifier> \"$qualifier\" . \n";
				}
				$buf .= "<$entryUri> <http://bio2rdf.org/goa_vocabulary:has_annotation> <http://bio2rdf.org/go:$go_id> .\n";
				foreach($db_references as $aref){
					$buf .= "<$entryUri> <http://bio2rdf.org/goa_vocabulary:has_source> \"".htmlentities($aref)."\" .\n";
				}
				$buf .= "<$entryUri> <http://bio2rdf.org/goa_vocabulary:has_evidence_code> <http://bio2rdf.org/goa_vocabulary:".htmlentities($parsedLine[6]).">. \n";
			
				$type = key($evidence_code);
				$buf .= "<http://bio2rdf.org/goa_vocabulary:$parsedLine[6]> <http://www.w3.org/2000/01/rdf-schema#label> \"".$evidence_code[$type][0]."\".\n";
				$buf .= "<http://bio2rdf.org/goa_vocabulary:$parsedLine[6]> <http://bio2rdf.org/goa_vocabulary:has_evidence_code_type> \"".$type."\".\n";
				$buf .= "<http://bio2rdf.org/goa_vocabulary:$parsedLine[6]> <http://www.w3.org/2000/01/rdf-schema#subClassOf> <".$evidence_code[$type][1]."> .\n";
				
				$buf .= "<$entryUri> <http://bio2rdf.org/goa_vocabulary:has_aspect_label> \"$aspectLabel\" .\n";
				$buf .= "<$entryUri> <http://bio2rdf.org/goa_vocabulary:has_gene_product> \"$geneProduct\" .\n";
				
				foreach($geneSynonyms as $aSyn){
					$buf .= "<$entryUri> <http://bio2rdf.org/goa_vocabulary:has_synonym> \"".htmlentities($aSyn)."\" .\n";
				}
				
				$buf .= "<$entryUri> <http://bio2rdf.org/goa_vocabulary:has_taxid> \"$taxid\" .\n";
				
				echo $buf;
			}
		}
	}
	if(!feof($fh)){
		echo "Error: unexpected fgetz() fail!\n";
	}
	gzclose($fh);
}


function getDbReferences($aDbReference){
	$a = explode("|",$aDbReference);
	return $a;
}
/**
 * This function returns the corresponding 
 * bio2rdf URI for the GOA entry for the given
 * db and db_id
 **/
function getdbURI($db_id, $db_object_id){
	$base = "http://bio2rdf.org/";
	$returnMe = "";
	if($db_id == "UniProtKB"){
		$returnMe = $base."uniprot:".$db_object_id;
	}
	return $returnMe;
}

function getTaxid($aTaxonLine){
	$a = explode(":",$aTaxonLine);
	return $a[1];
}

function getGeneSynonyms($aSynLine){
	$a = explode("|", $aSynLine);
	return $a;
}

function parse_goa_file_line($aGoALine){
	$returnMe = array();
	$lineArr = explode("\t",$aGoALine);
	//parse only annotation lines
	if(count($lineArr) == 17){
		return $lineArr;		
	}else{
		return null;
	}
	return $returnMe;
}

function getAspectLabel($anAspect){
	if(count($anAspect)){
		if($anAspect == "F"){
			return "molecular function";
		}elseif($anAspect == "P"){
			return "biological process";
		}elseif($anAspect == "C"){
			return "cellular component";
		}
		
	}else{
		return null;
	}
}



/**
 * This function return an array that has as a key
 * the name of the category to which the evidence code belongs
 * to and as a value the label for the code.
 * For example the evidence code "EXP" will return:
 * ["Experimental Evidence Code" => ["Inferred from Experiment", "http://purl.obolibrary.org/obo/ECO_0000006"]]
 * See: http://www.geneontology.org/GO.evidence.shtml
 **/
function getEvidenceCodeLabelArr($aec){
	if(count($aec)){
		//experimental code
		$ec = array(
			"EXP"=> array("Inferred from Experiment","http://purl.obolibrary.org/obo/ECO_0000006"),
			"IDA"=> array("Inferred from Direct Assay","http://purl.obolibrary.org/obo/ECO_0000314"),
			"IPI"=> array("Inferred from Physical Interaction","http://purl.obolibrary.org/obo/ECO_0000021"),
			"IMP"=> array("Inferred from Mutant Phenotype", "http://purl.obolibrary.org/obo/ECO_0000315"),
			"IGI"=> array("Inferred from Genetic Interaction","http://purl.obolibrary.org/obo/ECO_0000316"),
			"IEP"=> array("Inferred from Expression Pattern", "http://purl.obolibrary.org/obo/ECO_0000008")
		);
		//computational analysis codes
		$cac = array(
			"ISS"=> array("Inferred from Sequence or Structural Similarity","http://purl.obolibrary.org/obo/ECO_0000027"),
			"ISO"=> array("Inferred from Sequence Orthology", "http://purl.obolibrary.org/obo/ECO_0000201"),
			"ISA"=> array("Inferred from Sequence Alignment", "http://purl.obolibrary.org/obo/ECO_0000200"),
			"ISM"=> array("Inferred from Sequence Model", "http://purl.obolibrary.org/obo/ECO_0000202"),
			"IGC"=> array("Inferred from Genomic Context", "http://purl.obolibrary.org/obo/ECO_0000317"),
			"IBA"=> array("Inferred from Biological aspect of Ancestor","http://purl.obolibrary.org/obo/ECO_0000318"),
			"IBD"=> array("Inferred from Biological aspect of Desendant", "http://purl.obolibrary.org/obo/ECO_0000319"),
			"IKR"=> array("Inferred from Key Residues","http://purl.obolibrary.org/obo/ECO_0000320"),
			"IRD"=> array("Inferred from Rapid Divergence","http://purl.obolibrary.org/obo/ECO_0000321"),
			"RCA"=> array("Inferred from Reviewed Computational Analysis","http://purl.obolibrary.org/obo/ECO_0000245")
		);
		//author statement codes
		$asc = array(
			"TAS"=> array("Traceable Author Statement","http://purl.obolibrary.org/obo/ECO_0000304"),
			"NAS"=> array("Non-Traceable Author Statement","http://purl.obolibrary.org/obo/ECO_0000303")
		);
		//curator statement codes
		$csc = array(
			"IC"=> array("Inferred by Curator","http://purl.obolibrary.org/obo/ECO_0000001"),
			"ND"=> array("No biological Data available","http://purl.obolibrary.org/obo/ECO_0000035")
		);
		//automatically assigned codes
		$aac = array(
			"IEA"=>array("Inferred from Electronic Annotation", "http://purl.obolibrary.org/obo/ECO_0000203")
		);

		
		if(array_key_exists($aec, $ec)){
			return array("experimental evidence code"=>$ec[$aec]);
		}elseif(array_key_exists($aec, $cac)){
			return array("computational analysis code"=>$cac[$aec]);
		}elseif(array_key_exists($aec, $asc)){
			return array("author statement code"=>$asc[$aec]);
		}elseif(array_key_exists($aec, $csc)){
			return array("curator statement code"=>$csc[$aec]);
		}elseif(array_key_exists($aec, $aac)){
			return array("automatically assigned code"=>$aac[$aec]);
		}elseif(array_key_exists($aec, $oec)){
			return array("obsolete evidence code"=>$oec[$aec]);
		}else{
			return null;
		}
		
	}else{
		return null;
	}
	
	
	
}

?>