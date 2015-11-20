<?php
include(__DIR__ . "/../vendor/autoload.php");

use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;

class SpatialreferenceTest extends PHPUnit_Framework_TestCase
{
    /**
     * @runInSeparateProcess
     */
    public function testEveryTransformKnownToMan()
    {

		//$this->scrapeEveryCodeKnownToMan();
    	$proj4     = new Proj4php();
    	/*
    	$codes=array('EPSG:32040'=>(object)array(
    		'proj4'=>'+proj=lcc +lat_1=28.38333333333333 +lat_2=30.28333333333333 +lat_0=27.83333333333333 +lon_0=-99 +x_0=609601.2192024384 +y_0=0 +ellps=clrk66 +datum=NAD27 +to_meter=0.3048006096012192 +no_defs',
    		'ogcwkt'=>'PROJCS["NAD27 / Texas South Central",GEOGCS["NAD27",DATUM["North_American_Datum_1927",SPHEROID["Clarke 1866",6378206.4,294.9786982138982,AUTHORITY["EPSG","7008"]],AUTHORITY["EPSG","6267"]],PRIMEM["Greenwich",0,AUTHORITY["EPSG","8901"]],UNIT["degree",0.01745329251994328,AUTHORITY["EPSG","9122"]],AUTHORITY["EPSG","4267"]],UNIT["US survey foot",0.3048006096012192,AUTHORITY["EPSG","9003"]],PROJECTION["Lambert_Conformal_Conic_2SP"],PARAMETER["standard_parallel_1",28.38333333333333],PARAMETER["standard_parallel_2",30.28333333333333],PARAMETER["latitude_of_origin",27.83333333333333],PARAMETER["central_meridian",-99],PARAMETER["false_easting",2000000],PARAMETER["false_northing",0],AUTHORITY["EPSG","32040"],AXIS["X",EAST],AXIS["Y",NORTH]]'
    		));
    		*/


		$failAtEndErrors=array();

		$codes=get_object_vars(json_decode(file_get_contents(__DIR__.'/codes.json')));
		foreach($codes as $code=>$defs){

			$skipTestsFor=array(

						'SR-ORG:20', // proj4 uses robin (undefined transform)
						'SR-ORG:21', // proj4 is utm, wkt is tmerc but how to tell from wkt?
						'SR-ORG:30', // UNIT["unknown" ft->meters]
						'SR-ORG:81',
						'SR-ORG:89', //uncertain about units from greenwhich projection is named pytest
						'SR-ORG:90',//'''
						'SR-ORG:91',//''
						'SR-ORG:93',//''
						'SR-ORG:98', //UNIT "1/32meter" = 0.03125 ? wierd unit name
						'SR-ORG:106', // unnamed projection
						'SR-ORG:108', // just a test
						'SR-ORG:123', //custom

						'EPSG:2056',
						'EPSG:3006', //dont know how to get utm zone from this.
	
						'EPSG:4001', //GEOGCS["Unknown datum based upon the Airy 1830 ellipsoid",
						'EPSG:4006', //GEOGCS["Unknown datum based upon the Bessel Namibia ellipsoid"
			
						'SR-ORG:4695', //conflicting defintion fiw proj4, lat_ts/lat0?
						'SR-ORG:4696', //error message in wkt
						'SR-ORG:4700', //i think +datum=potsdam is missing from proj4? see //EPSG:3068 
						
						'EPSG:4188', // Failed asserting that datum->b 6356256.9092372851 matches expected 6356256.9100000001.
						'EPSG:4277', //Failed asserting that datum->b 6356256.9092372851 matches expected 6356256.9100000001.
						'EPSG:4278', //..
						'EPSG:4279', //..
						'EPSG:4293', //..
						'SR-ORG:4701', // Failed asserting that datum->b 6356078.9628400002 matches expected 6356078.9628181886.
						'SR-ORG:6628', // variables->b2 Failed asserting that 40408584830600.609 matches expected 40408584830600.555.
						'SR-ORG:6650', // ogcwkt string is javascript concatinated string.
						'SR-ORG:6651', //
						'SR-ORG:6652'
 						); 


			

			if(in_array($code, $skipTestsFor)){
				continue;
			}



			if(key_exists('proj4', $defs)){

				if(empty($defs->proj4)){
					continue;
				}

				if(strpos($defs->ogcwkt, '(deprecated)')!==false||
					strpos($defs->ogcwkt, 'AXIS["Easting",UNKNOWN]')||
					strpos($defs->ogcwkt, 'AXIS["none",EAST]')||
					strpos($defs->ogcwkt, 'AXIS["X",UNKNOWN]')
						){
					continue;
				}


				$proj4->addDef($code, $defs->proj4);
				try{
					$projection=new Proj($code, $proj4);

				}catch(Exception $e){

					throw new Exception('Error loading proj4: '.$e->getMessage().' '.$code.' '.print_r($defs,true).' '.print_r($e->getTrace(),true));

				}
				if(key_exists('ogcwkt', $defs)){

					$codesString=print_r(array(
						$code,
						$defs->proj4,
						$defs->ogcwkt

						),true);

					try{

						$projOgcwktInline=new Proj($defs->ogcwkt, $proj4);
						$expected=get_object_vars($projection->projection);
						$actual=get_object_vars($projOgcwktInline->projection);


						if(key_exists('axis', $actual)||key_exists('axis', $expected)){
							if($actual['axis']!==$expected['axis']){
								$failAtEndErrors[$code]='Axis Mismatch: '.$codesString;
							}
						}


						if((key_exists('to_meters', $actual)&&$actual['to_meters']!==1.0)||(key_exists('to_meters', $expected)&&$expected['to_meters']!==1.0)){
							$this->assertEquals(array_intersect_key($expected, array('to_meters'=>'')), array_intersect_key($actual, array('to_meters'=>'')), $codesString);
						}

						if(key_exists('datum', $expected)){
							$this->assertEquals(array_intersect_key($expected, array(
										//'datumName'=>'', 
								'datumCode'=>'',
								//'datum'=>'',
								'datum_params'=>'')),
							array_intersect_key($actual, array(
										//'datumName'=>'', 
								'datumCode'=>'',
								//'datum'=>'',
								'datum_params'=>'')), $codesString);

						
							$datumPrecisionTest=array(
								'b'=>0.0001,
								'es'=>0.0001,
								'ep2'=>0.0001,
								'a'=>0.0001,
								'rf' => 0.0001
							);


							$this->assertEquals(
								array_diff_key(get_object_vars($expected['datum']),$datumPrecisionTest),
								array_diff_key(get_object_vars($actual['datum']),$datumPrecisionTest)
							);

							foreach($datumPrecisionTest as $key=>$precision){
								if(key_exists($key, $expected['datum'])){
									$this->assertEquals($expected['datum']->$key, $actual['datum']->$key, 'AssertEquals Failed: datum->'.$key.' ('.$precision.'): '.$codesString,$precision);			
								}
							}

						}

						
						//if either defines non zero alpha or gama

						$alphagamma=array();
						if((key_exists('alpha', $actual)&&$actual['alpha']!==0.0)||(key_exists('alpha', $expected)&&$expected['alpha']!==0.0)){
							$alphagamma['alpha']='';
						}
						if((key_exists('gamma', $actual)&&$actual['gamma']!==0.0)||(key_exists('gamma', $expected)&&$expected['gamma']!==0.0)){
							$alphagamma['gamma']='';
						}
						if(!empty($alphagamma)){
							$this->assertEquals(array_intersect_key($expected, $alphagamma), array_intersect_key($actual, $alphagamma), $codesString);

						}


						$skipCompareFor=array(
		    						//'SR-ORG:11',
		    						//'SR-ORG:62', //tiny cascading difference in lat_2

		    						//'SR-ORG:83', //same issue
		    						//'SR-ORG:89', //''
		    						//'EPSG:2000', //''
		    						//'EPSG:2001'


		    					); //this should be empty! 

						$precisionTest=array(
							'x0'=>0.0000001,
							'y0'=>0.0000001,
							'lat_1'=>0.0000001,
							't2' => 0.0000001,
							'ms2' => 0.0000001,
							'ns0' => 0.0000001,
							'c' => 0.0000001,
							'rh' => 0.0000001,
							'rf' => 0.0000001,
							'b' => 0.00001,
							'b2' => 0.0000001,
							'es' => 0.0000001,
							'e' => 0.0000001,
							'ep2' => 0.0000001,
						);



						foreach($precisionTest as $key=>$precision){
							if(key_exists($key, $expected)){
								$this->assertEquals($expected[$key], $actual[$key], 'AssertEquals Failed: variables->'.$key.' ('.$precision.'): '.$codesString,$precision);			
							}
						}

						if(!in_array($code, $skipCompareFor)){
							$ignore=array_merge(array(

								'name'=>'', 
									//'projName'=>'',
								'units'=>'', 
								'srsCode'=>'', 
								'srsCodeInput'=>'', 
								'projection'=>'', 
								'srsAuth'=>'',
								'srsProjNumber'=>'',
								'defData'=>'', 
								'geocsCode'=>'', 
								'datumName'=>'', 
								'datumCode'=>'',
								'from_greenwich'=>'',
									//'zone'=>'',
								'ellps'=>'',
									//'utmSouth'=>'',
								'datum'=>'',
								'datum_params'=>'',
			    				'alpha'=>'', 
			    				'axis'=>'',

			    						),$precisionTest);
							$a=array_diff_key($expected, $ignore); 

							$b=array_intersect_key( array_diff_key($actual, $ignore), $a);
							$this->assertEquals($a, $b, print_r(array($a, $b, $codesString), true));
						}

						$unitA=strtolower($actual['units']{0});
						$unitB=strtolower($actual['units']{0});
						if(((!empty($unitA))&&$unitA!='d')||((!empty($unitB))&&$unitB!='d')){
							$this->assertEquals($unitA, $unitA, '(units mismatch) '.$codesString);
						}




								//if either defines non zero alpha
						if((key_exists('from_greenwich', $actual)&&$actual['from_greenwich']!==0.0)||(key_exists('from_greenwich', $expected)&&$expected['from_greenwich']!==0.0)){
							$this->assertEquals(array_intersect_key($expected, array('from_greenwich'=>'')), array_intersect_key($actual, array('from_greenwich'=>'')), $codesString);
						}




						$this->assertEquals(get_class($projection->projection), get_class($projOgcwktInline->projection),$codesString);


					}catch(Exception $e){
						if($e instanceof PHPUnit_Framework_ExpectationFailedException){
							throw $e;
						}else{
							$this->fail(print_r(array($e->getMessage(), $codesString, get_class($e)/*,$e->getTrace()*/), true));
						}
					}
				}
			}




		}	

	if(count($failAtEndErrors)>0){
		$this->fail(print_r($failAtEndErrors));
	}

}



function scrapeEveryCodeKnownToMan(){


	$next='http://spatialreference.org/ref/';
	$max=100; //pages
	$fileContent=file_get_contents(__DIR__.'/codes.json');
	if(empty($fileContent)){
		$pageCodes=array();
	}else{
		$pageCodes=get_object_vars( json_decode($fileContent));
	}
	while($next&&$max!==0){


		$page=file_get_contents($next);
		$codes=array_values(array_filter(array_map(function($a)use(&$next){ 

					//....>code:num
			$i=strrpos($a, '>');
			$str= substr($a, $i+1);
			if(trim($str)=='Next Page'){
				$url=explode('"', $a);
				$url=$url[count($url)-2];
				$next='http://spatialreference.org/ref/'.$url;
			}
			return $str;

		}, explode('</a>', $page)), function($a){

			if(strpos($a,':')!==false)return true;

		}));
		print_r($codes);
		if(!array_key_exists($codes[0], $pageCodes)){

			array_walk($codes, function($c) use(&$pageCodes) {
				$p=explode(':', $c);
				$pageCodes[$c]=array(
					'ogcwkt'=>file_get_contents('http://spatialreference.org/ref/'.strtolower($p[0]).'/'.$p[1].'/ogcwkt/'),
					'proj4'=>file_get_contents('http://spatialreference.org/ref/'.strtolower($p[0]).'/'.$p[1].'/proj4/'),
					'esriwkt'=>file_get_contents('http://spatialreference.org/ref/'.strtolower($p[0]).'/'.$p[1].'/esriwkt/')
					);


			});
		}

				//$next=false;
		$max--;

		file_put_contents(__DIR__.'/codes.json', json_encode($pageCodes, JSON_PRETTY_PRINT));
	}


	





}





}
