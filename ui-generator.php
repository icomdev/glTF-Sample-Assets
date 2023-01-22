<?php
/*
 * Script for updating all 2.0 model directories
 *
 * Steps:
 *	Read in model-metadata.json template at repo root directory
 *  For each folder in ./2.0/
 *		Open metadata.json file (local-data)
 *			If no file, then create it with initial values from template+
 *		Populate template with local-data
 *		Report any missing mandatory fields
 *		Create (Update) UI files
 *			README1.md (model-specific)
 *			<tag-N>.md (store in data-structure for later creation)
 *			dep5 for licenses
 *		<end-create>
 *	<end-for>
 *
 **/
 // Metadata JSON template file
$Templates['Metadata']= ['file'=>'./metadata.template.json', 'type'=>'JSON', 'version'=>1, 'model'=>'metadata.json'];

// README template file
$Templates['Readme'] = ['file'=>'./README.template.md', 'type'=>'MD', 'outputName'=>'README1.md'];

// Process Model directory
CreateUI ('./2.0', $Templates);
exit;

// Function for processing an entire folder of model directories
function CreateUI ($modelFolder, $Templates) {

// Get Metadata template structure
	$Templates['Metadata']['Structure'] = getFileStructure ($Templates['Metadata']);

// Get README template structure
	$Templates['Readme']['Structure'] = getFileStructure ($Templates['Readme']);

/*
 * Create output structure
 *	Each element describes a single model and contains
 *		Path to model directory
 *		Name of model
 *		Array of applicable tags
 *		License of entire model (use <multiple> to indicate incompatible licenses)
 *		Array of licenses where each element identifies a file and its associated license(s)
 *		Copyright year
 *		Copyright owner (string)
 **/
	$Models = [];

// Loop through all matching directories
	$folder = dir ($modelFolder);
	//print "Path: ".$folder->path."; Handle: ".$folder->handle.";\n";
	$folderDotDirs = array ($modelFolder.'/.', $modelFolder.'/..');

	$F = fopen ('modelMetadata.csv', 'w');
	while (false !== ($model = $folder->read())) {
		$modelDir = $folder->path . '/' . $model;
		$metaFilename = $Templates['Metadata']['model'];
		if (is_dir($modelDir) && !($model == '.' || $model == '..')) {
			$metadata = getMetadata ($modelDir, $metaFilename, ['name'=>$model], $Templates['Metadata']);
			fwrite ($F, sprintf ('%s,%s,%s,%s,%s,%04d,"%s"'."\n", $metadata->{'key'}, $metadata->{'name'}, $metadata->{'path'}, $metadata->{'author'}, $metadata->{'owner'}, $metadata->{'year'}, join(' ', $metadata->{'license'})));
			$metaAll[] = $metadata;
		}
	}
	fclose ($F);
	$folder->close();
	//print "Processed all models\n";
	//print_r($Templates);
	
	createReadme ('Image', 'README.image.md', $metaAll, array());
	createReadme ('List', 'README.all.md', $metaAll, array());
	
	return;
}

// Function for creating READMEs
function createReadme ($type, $fname, $metaAll, $tags) {
	$F = fopen ($fname, 'w');
	$section = 'Tagged...';
	if (count($tags) == 0) {
		$section = 'All models';
	}
	
	fwrite ($F, "# glTF 2.0 Sample Models\n\n");
	fwrite ($F, "## $section\n\n");
	
	if ($type == 'Image') {
		$fmtString = "[![%s](%s)](%s)\n";
		for ($ii=0; $ii<count($metaAll); $ii++) {
			fwrite ($F, sprintf ($fmtString, 
						$metaAll[$ii]->{'name'}, 
						$metaAll[$ii]->{'UriHeight'},
						$metaAll[$ii]->{'UriReadme'}
						));
		}
		
	} else if ($type == 'List') {
		fwrite ($F, "| Model   | Screenshot  | Description |\n");
		fwrite ($F, "|---------|-------------|-------------|\n");
		$fmtString = "| [%s](%s) | ![](%s) | %s |\n";

		for ($ii=0; $ii<count($metaAll); $ii++) {
//| [Metal Rough Spheres](MetalRoughSpheres)              | ![](MetalRoughSpheres/screenshot/screenshot.png)              | Tests various metal and roughness values (texture mapped). |
//| [%s](%s) | ![]($s) | %s |
			fwrite ($F, sprintf ($fmtString, 
						$metaAll[$ii]->{'name'}, 
						$metaAll[$ii]->{'UriReadme'},
						$metaAll[$ii]->{'UriShot'},
						$metaAll[$ii]->{'summary'},
						));
		}
	}
	fclose ($F);
	return;
}

// Function to return the model's metadata
// This may need to create the file
function getMetadata ($modelDir, $metaFilename, $Defaults, $Metadata) {
	$filename = $modelDir . '/' . $metaFilename;
	if (file_exists($filename)) {
		$string = file_get_contents ($filename);
		$metadata = json_decode ($string);
		$needsWriting = false;

	} else {
		$metadata = $Metadata['Structure'];
		$needsWriting = true;
	}
	
	if (!isset($metadata->{'version'}) || $metadata->{'version'} != $Metadata['version']) {
		$metadata = updateMetadata ($metadata, $modelDir, $Defaults, $Metadata['Structure']);
		$needsWriting = true;
	}
	
	if ($needsWriting) {
		$string = json_encode($metadata, JSON_PRETTY_PRINT);
		$FH = fopen ($filename, "w");
		fwrite ($FH, $string);
		fclose ($FH);
	}

	return $metadata;
}

/* 
 * Function to update Metadata structure 
 * Supports all versions
 * Initial release supports upgrade from 0 to 1
 *	0 is basic created from runtime
 *	1 is standard release with all available information
 *
 *	V0 needs information loaded from existing README
 **/
function updateMetadata ($metadata, $dir, $Defaults, $Structure) {
	if (!isset($metadata->{'version'}) || $metadata->{'version'} == 0) {
		//print "Updating metadata with info from $dir/README.md\n";
		$string = file_get_contents ($dir . '/README.md');
		$readme = explode (PHP_EOL, $string);
		$modelName = $metadata->{'name'};
		$metadata = clone $Structure;
		$license = (isset($metadata->{'license'})) ? $metadata->{'license'} : [];
		$description = (isset($metadata->{'description'})) ? $metadata->{'description'} : [];
		
		if (substr($readme[0], 0, 2) == '# ') {
			$modelName = substr($readme[0], 2);
			//print " ... Updating name\n";
		}
		
// Description may or may not be in the file. It starts at '## Description' and runs until 
//	the end of file or another section starting with '## '
		$shortDescription = '... no description ...';
		for ($ii=0; $ii<count($readme)-1; $ii++) {
			if ($readme[$ii] == '## Description') {
				$shortDescription = '... nothing ...';
				$description = array();
				for ($jj=$ii+1; $jj<count($readme); $jj++) {
					if (substr($readme[$jj], 0, 3) == '## ') {
						break;
					} else if ($readme[$jj] != '') {
						$description[] = $readme[$jj];
					}
				}
				if (count($description) != 0) {
					$shortDescription = $description[0];
				}
				//print " ... Updating description\n";
			}
		
// License is last section in the file ** ASSUMPTION **
			//print "[$ii] |".$readme[$ii]."|\n";
			if ($readme[$ii] == '## License Information') {
				for ($jj=$ii+1; $jj<count($readme); $jj++) {
					if (rtrim($readme[$jj]) != '') {
						$license[] = $readme[$jj];
					}
				}
				//print " ... Updating license\n";
			}
		}
	}

	foreach ($Structure as $key => $value) {
		if (!isset($metadata->{$key})) {$metadata->{$key} = $value;}
	}
	
	$screenshot = 'screenshot/screenshot';
	$metadata->{'screenshotType'} = (file_exists($dir.'/'.$screenshot.'.jpg')) ? 'jpg' : ((file_exists($dir.'/'.$screenshot.'.png')) ? 'png' : 'gif');
// Create standard-height image
	if ($metadata->{'screenshotType'} == 'jpg' || $metadata->{'screenshotType'} == 'png') {
		$shotHeight = createScreenShot ($dir, $screenshot, $metadata->{'screenshotType'}, 150);
	} else {
		$shotHeight = $screenshot;
	}

	$metadata->{'version'} = 0;
	$metadata->{'name'} = $modelName;
	$metadata->{'key'} = $Defaults['name'];
	$metadata->{'path'} = 'https://github.com/KhronosGroup/glTF-Sample-Models/tree/master/' . $dir;
	$metadata->{'screenshot'} = $screenshot . '.' . $metadata->{'screenshotType'};
	$metadata->{'pathShot'} = $dir . '/' . $metadata->{'screenshot'};
	$metadata->{'UriShot'} = rawurlencode($metadata->{'pathShot'});
	$metadata->{'shotHeight'} = $shotHeight . '.' . $metadata->{'screenshotType'};
	$metadata->{'pathHeight'} = $dir . '/' . $metadata->{'shotHeight'};
	$metadata->{'UriHeight'} = rawurlencode($metadata->{'pathHeight'});
	$metadata->{'UriReadme'} = rawurlencode($dir . '/README.md');
	$metadata->{'description'} = $description;
	$metadata->{'summary'} = $shortDescription;
	$metadata->{'license'} = $license;
	$metadata->{'createReadme'} = true;

	return $metadata;
}

// Function to create standard size screenshots
function createScreenShot ($path, $shotOriginal, $shotType, $imageHeight) {
	$shotOut = sprintf ('%s-x%d', $shotOriginal, $imageHeight);
	$cmd = sprintf ('magick %s/%s.%s -background white -resize %d %s/%s.%s',
						$path, $shotOriginal, $shotType,
						$imageHeight,
						$path, $shotOut, $shotType);
	//print "$cmd\n";
	system ($cmd);
	return $shotOut;
}

// Function to read and parse the specified file based on a structure
// File I/O errors are fatal
function getFileStructure ($templateStructure) {
	if (! file_exists($templateStructure['file'])) {
		print "Unable to find template: " . $templateStructure['file'] . "\nAborting\n";
		die (2);
	}
	$string = file_get_contents ($templateStructure['file']);
	//print "Template file ($".$templateStructure['file']."): $string\n";
	
	// Parse the file contents based on the type 
	if ($templateStructure['type'] == 'JSON') {
		$retval = json_decode ($string);
	} else if ($templateStructure['type'] == 'MD') {
		$retval = explode (PHP_EOL, $string);
	} else {
		$retval = $string;
	}
	//print "Structured template:\n";
	//print_r ($retval);
	return $retval;
}