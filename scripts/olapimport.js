// RDFa
var attributeModel = "http://localhost/ontowiki/sdmx-attribute";
var conceptModel   = "http://localhost/ontowiki/sdmx-concept";
var dimensionModel = "http://localhost/ontowiki/sdmx-dimension";



function getDimensions(table){		

		

}; 
//var mapping= new Array();
var mapping = {};
var uribase = '';
var attribute='';
var measure='';
var dataset='';
$(document).ready(function () {
	$('#dimensions input[type=text]').live('keyup',function(e){
		var type=$(this).attr('name');
		var value = $(this).val();
		var array = type.split('@'); //array[0] type of input array[1] dimension table
		var url_dimension=uribase+"/dimension/"+urlencode(array[1]);
	
		if(array[0]=='dimension_label'){
			mapping[url_dimension]['label_dimension']=value;
		}else if(array[0]=='subPropertyOf'){
			mapping[url_dimension]['subproperty']=value;
		}else if(array[0]=='concept'){
			mapping[url_dimension]['concept']=value;
		}else if(array[0]=='attribute'){
	
			delete mapping[attribute];

			var url_attribute=uribase+"/attribute/"+urlencode(value);
			attribute=url_attribute;				
			mapping['data']['attributes']={};
			
			mapping[url_attribute]={};
			mapping[url_attribute]['type']="attribute";
			mapping[url_attribute]['attribute']="true";
			mapping[url_attribute]['label']=value;
			mapping[url_attribute]['uri']="http://purl.org/linked-data/sdmx/2009/attribute#unitMeasure";
		}else if(array[0]=='measure'){	
			delete mapping[measure];
		
			mapping['data']['measures']={};

			var url_measure=uribase+"/measure/"+urlencode(value);
			measure=url_measure;
	
			mapping[url_measure]={};
			mapping[url_measure]['type']="measure";			
			mapping[url_measure]['measure']="true";
			mapping[url_measure]['label']=value;
			mapping[url_measure]['range']="http://www.w3.org/2001/XMLSchema#integer";
			mapping[url_measure]['uri']="http://purl.org/linked-data/sdmx/2009/measure#obsValue";	

			
		}else if(array[0]=='dataset'){
			delete mapping[dataset];

			mapping['data']['datasets']={};	
						
			var url_dataset=uribase+"/dataset";
			dataset=url_dataset;

			mapping[url_dataset]={};
			mapping[url_dataset]['type']="dataset";			
			mapping[url_dataset]['dataset']="true";
			mapping[url_dataset]['label']=value;
			mapping[url_dataset]['comment']=value;		

		}
		
	});	

	$(':checkbox').live('click',function() 
	{
		var _class=$(this).attr('class');
		var _value=$(this).val();
		var isChecked=$(this).is(':checked');
		//alert("_class.: "+_class+ " _value.: "+ _value +" isChecked.:"+isChecked);		
		//alert($.toJSON(array));
		
		//controller cubes
		if(_class=="fact_table" && isChecked==true){
			mapping = {};			
			uribase =$('#uribase').val(); //get URL base
			mapping['uribase']=uribase;

			mapping['data']= {};
			mapping['data']['type']="fact";
			mapping['data']['fact']="true";
			mapping['data']['table']=_value;
			mapping['data']['uribase']=uribase;
			mapping['data']['dimensions']={};
			mapping['data']['attributes']={};
			mapping['data']['measures']={};
			mapping['data']['datasets']={};					
			mapping['data']['lines']="1000"; //default
			//alert($.toJSON(mapping));
			
		}else if(_class=="fact_table" && isChecked==false){
		//	alert("//cubo deve ser retirado do mapeamento dos cubos");
		}else if(_class=="fact_measure" && isChecked==true){
			var array = _value.split('@');
			//alert("Table: "+array[0]+" attribute: "+array[1]);

			mapping['data']['value']=array[1];
			mapping['data']['column']=array[1];
			

			var url = window.location.href + '/cubes';
			$.post(url,{table: array[0]} ,function(data){
				var div_str = data;								
				$('#div_mapping').html(div_str);							
			});
		}else if(_class=="fact_measure" && isChecked==false){

		}else if(_class=="dimension_table" && isChecked==true){	
			var array = _value.split('@'); //array[0] key from fact, array[1] table referenced, array[2] key referenced			
			var url_dimension=uribase+"/dimension/"+urlencode(array[1]);	
			
			mapping['data']['dimensions'][url_dimension]={};
			mapping['data']['dimensions'][url_dimension]['from']=array[0];
			mapping['data']['dimensions'][url_dimension]['to']=array[2];
			mapping['data']['dimensions'][url_dimension]['to_table']=array[1];
										
			mapping[url_dimension]={};
			mapping[url_dimension]['type']="dimension";
			mapping[url_dimension]['dimension']="true";
			mapping[url_dimension]['default']="false";
			mapping[url_dimension]['label_dimension']=array[2];
			mapping[url_dimension]['table']=array[1];
			mapping[url_dimension]['subproperty']=url_dimension;
			mapping[url_dimension]['concept']=url_dimension;
			mapping[url_dimension]['uribase']=uribase;
	
					
						
			//mapping['data']['dimensions']['']	
		}else if(_class=="dimension_table" && isChecked==false){
			//alert(_value);				
		}else if(_class=="dimension_column" && isChecked==true){
			
			var array = _value.split('@'); //array[0] table referenced, array[1] column 
			var url_dimension=uribase+"/dimension/"+urlencode(array[0]);			
			mapping['data']['dimensions'][url_dimension]['column']=array[1];	

			mapping[url_dimension]['column']=array[1];
			
		}else if(_class=="dimension_column" && isChecked==false){
			//alert(_value);
		}else if(_class=="attribute_table" && isChecked==true){
			var array = _value.split('@'); //array[0] key from fact, array[1] table referenced, array[2] key referenced		
			var url_attribute=uribase+"/attribute/"+urlencode(array[1]);
					
			mapping['data']['attributes'][url_attribute]={};
			mapping['data']['attributes'][url_attribute]['from']=array[0];
			mapping['data']['attributes'][url_attribute]['to']=array[2];
			mapping['data']['attributes'][url_attribute]['to_table']=array[1];

			mapping[url_attribute]={};
			mapping[url_attribute]['type']="attribute";
			mapping[url_attribute]['attribute']="true";
			mapping[url_attribute]['label']=array[1];
			mapping[url_attribute]['bytable']="true";			
			mapping[url_attribute]['table']=array[1];	
			mapping[url_attribute]['uri']="http://purl.org/linked-data/sdmx/2009/attribute#unitMeasure";

		}else if(_class=="attribute_table" && isChecked==false){
			a//lert(_value);				
		}else if(_class=="attribute_column" && isChecked==true){		
			var array = _value.split('@'); //array[0] table referenced, array[1] column 
			var url_attribute=uribase+"/attribute/"+urlencode(array[0]);

			mapping['data']['attributes'][url_attribute]['column']=array[1];	
			mapping[url_attribute]['column']=array[1];
				
		}else if(_class=="attribute_column" && isChecked==false){
			//alert(_value);
		}else if(_class=="measure_table" && isChecked==true){
			var array = _value.split('@'); //array[0] key from fact, array[1] table referenced, array[2] key referenced		
			var url_measure=uribase+"/measure/"+urlencode(array[1]);

			mapping['data']['measures'][url_measure]={};
			mapping['data']['measures'][url_measure]['from']=array[0];
			mapping['data']['measures'][url_measure]['to']=array[2];
			mapping['data']['measures'][url_measure]['to_table']=array[1];

			mapping[url_measure]={};
			mapping[url_measure]['type']="measure";			
			mapping[url_measure]['measure']="true";
			mapping[url_measure]['label']=array[1];
			mapping[url_measure]['bytable']="true";	
			mapping[url_measure]['table']=array[1];
			mapping[url_measure]['range']="http://www.w3.org/2001/XMLSchema#integer";
			mapping[url_measure]['uri']="http://purl.org/linked-data/sdmx/2009/measure#obsValue";	

		}else if(_class=="measure_table" && isChecked==false){
			//alert(_value);				
		}else if(_class=="measure_column" && isChecked==true){
			var array = _value.split('@'); //array[0] table referenced, array[1] column 
			var url_measure=uribase+"/measure/"+urlencode(array[0]);
			
			mapping['data']['measures'][url_measure]['column']=array[1];				
			mapping[url_measure]['column']=array[1];
				
		}else if(_class=="measure_column" && isChecked==false){
			//alert(_value);
		}else if(_class=="dataset_table" && isChecked==true){
			var array = _value.split('@'); //array[0] key from fact, array[1] table referenced, array[2] key referenced		
			var url_dataset=uribase+"/dataset";

			mapping['data']['datasets'][url_dataset]={};
			mapping['data']['datasets'][url_dataset]['from']=array[0];
			mapping['data']['datasets'][url_dataset]['to']=array[2];
			mapping['data']['datasets'][url_dataset]['to_table']=array[1];			

			mapping[url_dataset]={};
			mapping[url_dataset]['type']="dataset";			
			mapping[url_dataset]['dataset']="true";
			mapping[url_dataset]['label']=array[1];
			mapping[url_dataset]['bytable']="true";
			mapping[url_dataset]['table']=array[1];
			mapping[url_dataset]['comment']=array[1];		

		}else if(_class=="dataset_table" && isChecked==false){
			//alert(_value);				
		}else if(_class=="dataset_column" && isChecked==true){
			var array = _value.split('@'); //array[0] table referenced, array[1] column 
			var url_dataset=uribase+"/dataset";
			
			mapping['data']['datasets'][url_dataset]['column']=array[1];			
			mapping[url_dataset]['column']=array[1];			
	
		}else if(_class=="dataset_column" && isChecked==false){
			//alert(_value);
		}	

	} );
	
	
		    
	
   
});


function urlencode( str ) {
 
    var histogram = {}, tmp_arr = [];
    var ret = (str+'').toString();
 
    var replacer = function(search, replace, str) {
        var tmp_arr = [];
        tmp_arr = str.split(search);
        return tmp_arr.join(replace);
    };
 
    // The histogram is identical to the one in urldecode.
    histogram["'"]   = '%27';
    histogram['(']   = '%28';
    histogram[')']   = '%29';
    histogram['*']   = '%2A';
    histogram['~']   = '%7E';
    histogram['!']   = '%21';
    histogram['%20'] = '+';
    histogram['\u20AC'] = '%80';
    histogram['\u0081'] = '%81';
    histogram['\u201A'] = '%82';
    histogram['\u0192'] = '%83';
    histogram['\u201E'] = '%84';
    histogram['\u2026'] = '%85';
    histogram['\u2020'] = '%86';
    histogram['\u2021'] = '%87';
    histogram['\u02C6'] = '%88';
    histogram['\u2030'] = '%89';
    histogram['\u0160'] = '%8A';
    histogram['\u2039'] = '%8B';
    histogram['\u0152'] = '%8C';
    histogram['\u008D'] = '%8D';
    histogram['\u017D'] = '%8E';
    histogram['\u008F'] = '%8F';
    histogram['\u0090'] = '%90';
    histogram['\u2018'] = '%91';
    histogram['\u2019'] = '%92';
    histogram['\u201C'] = '%93';
    histogram['\u201D'] = '%94';
    histogram['\u2022'] = '%95';
    histogram['\u2013'] = '%96';
    histogram['\u2014'] = '%97';
    histogram['\u02DC'] = '%98';
    histogram['\u2122'] = '%99';
    histogram['\u0161'] = '%9A';
    histogram['\u203A'] = '%9B';
    histogram['\u0153'] = '%9C';
    histogram['\u009D'] = '%9D';
    histogram['\u017E'] = '%9E';
    histogram['\u0178'] = '%9F';
 
    // Begin with encodeURIComponent, which most resembles PHP's encoding functions
    ret = encodeURIComponent(ret);
 
    for (search in histogram) {
        replace = histogram[search];
        ret = replacer(search, replace, ret) // Custom replace. No regexing
    }
 
    // Uppercase for full PHP compatibility
    return ret.replace(/(\%([a-z0-9]{2}))/g, function(full, m1, m2) {
        return "%"+m2.toUpperCase();
    });
 
    return ret;
}

