<?php

require_once('vendor/autoload.php');

$folder = "../data";
$outFolder = "../output";

// ---

if (!file_exists($outFolder)) {
	mkdir($outFolder);
}

$statistics = [];

$statistics["creender"] = [];
$statistics["rc"] = [];

$statistics["creender"]["pictures"] = [];
$statistics["creender"]["sets"] = [];
$statistics["creender"]["reported"] = [];

$statistics["rc"]["users"] = 0;
$statistics["rc"]["messages"] = 0;
$statistics["rc"]["sessions"] = 0;

$files = scandir($folder);
foreach ($files as $file) {
	if ($file[0] == "~") {
		continue;
	}
	if (strpos($file, "creender") !== false) {
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load("$folder/$file");
		$highestRow = $spreadsheet->getActiveSheet()->getHighestRow();
		for ($i = 2; $i <= $highestRow; $i++) {
			$id = $spreadsheet->getActiveSheet()->getCell('C'.$i)->getValue();
			$filename = $spreadsheet->getActiveSheet()->getCell('E'.$i)->getValue();
			$answer = $spreadsheet->getActiveSheet()->getCell('F'.$i)->getValue();
			if ($answer == "Report") {
				if (!isset($statistics["creender"]["reported"][$id])) {
					$statistics["creender"]["reported"][$id] = ["num" => 0, "filename" => $filename];
				}
				$statistics["creender"]["reported"][$id]["num"]++;
				continue;
			}

			$set = $spreadsheet->getActiveSheet()->getCell('D'.$i)->getValue();
			$typesText = $spreadsheet->getActiveSheet()->getCell('G'.$i)->getValue();
			$types = [];
			if ($typesText) {
				$types = explode(",", $typesText);
				$types = array_map("trim", $types);
			}

			// Pics
			if (!isset($statistics["creender"]["pictures"][$id])) {
				$statistics["creender"]["pictures"][$id] = [
					"set" => $set,
					"filename" => $filename,
					"answers" => [
						"Yes" => 0,
						"No" => 0
					],
					"types" => [],
					"types_normalized" => []
				];
			}
			$statistics["creender"]["pictures"][$id]['answers'][$answer]++;
			foreach ($types as $t) {
				if (!isset($statistics["creender"]["pictures"][$id]['types'][$t])) {
					$statistics["creender"]["pictures"][$id]['types'][$t] = 0;
					$statistics["creender"]["pictures"][$id]['types_normalized'][$t] = 0;
				}
				$statistics["creender"]["pictures"][$id]['types'][$t]++;
				$statistics["creender"]["pictures"][$id]['types_normalized'][$t] += round(1 / (count($types)), 2);
			}

			// Sets
			if (!isset($statistics["creender"]["sets"][$set])) {
				$statistics["creender"]["sets"][$set] = ["answers" => ["Yes" => 0, "No" => 0], "types" => [], "types_normalized" => []];
			}
			$statistics["creender"]["sets"][$set]['answers'][$answer]++;
			foreach ($types as $t) {
				if (!isset($statistics["creender"]["sets"][$set]['types'][$t])) {
					$statistics["creender"]["sets"][$set]['types'][$t] = 0;
					$statistics["creender"]["sets"][$set]['types_normalized'][$t] = 0;
				}
				$statistics["creender"]["sets"][$set]['types'][$t]++;
				$statistics["creender"]["sets"][$set]['types_normalized'][$t] += round(1 / (count($types)), 2);
			}
		}
		// echo "$file\n";
	}
	if (strpos($file, "rc") !== false) {
		$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load("$folder/$file");

		$sessions = $spreadsheet->getActiveSheet()->getCell('B3')->getValue();
		$statistics["rc"]["sessions"] += $sessions;

		$highestRow = $spreadsheet->getActiveSheet()->getHighestRow();
		$isMessages = false;
		for ($i = 1; $i <= $highestRow; $i++) {
			$content = $spreadsheet->getActiveSheet()->getCell('A'.$i)->getValue();
			if ($content == "Users") {
				$isMessages = true;
				continue;
			}
			if (trim($content) == "") {
				$isMessages = false;
			}
			if ($isMessages) {
				$value = $spreadsheet->getActiveSheet()->getCell('C'.$i)->getValue();
				if ($value > 0) {
					$statistics["rc"]["users"]++;
					$statistics["rc"]["messages"] += $value;
				}
			}
		}
	}
}

$total = ["answers" => ["Yes" => 0, "No" => 0], "types" => [], "types_normalized" => []];
foreach ($statistics['creender']['sets'] as $name => $values) {
	$total['answers']['Yes'] += $values['answers']["Yes"];
	$total['answers']['No'] += $values['answers']["No"];
	foreach (['types', 'types_normalized'] as $t) {
		foreach ($values[$t] as $type => $v) {
			if (!isset($total[$t][$type])) {
				$total[$t][$type] = 0;
			}
			$total[$t][$type] += $v;
		}
	}
}

$statistics["creender"]['sets']["total"] = $total;
foreach ($statistics['creender']['sets'] as $name => $values) {
	$statistics['creender']['sets'][$name]["answers"]["ratio"] =
		round($values['answers']["Yes"] / ($values['answers']["Yes"] + $values['answers']["No"]), 2);
	$sum = array_sum($values["types_normalized"]);
	$statistics['creender']['sets'][$name]['ratio_normalized'] = [];
	foreach ($values["types_normalized"] as $t => $v) {
		$statistics['creender']['sets'][$name]['ratio_normalized'][$t] = round($v / $sum, 2);
	}
}

$fp = fopen($outFolder . "/photos.tsv", "w");
foreach ($statistics['creender']['pictures'] as $id => $pic) {
	$ratio = round($pic['answers']['Yes'] / ($pic['answers']['Yes'] + $pic['answers']['No']), 2);
	fwrite($fp, $id);
	fwrite($fp, "\t");
	fwrite($fp, $pic['set']);
	fwrite($fp, "\t");
	fwrite($fp, $pic['filename']);
	fwrite($fp, "\t");
	fwrite($fp, $pic['answers']['Yes']);
	fwrite($fp, "\t");
	fwrite($fp, $pic['answers']['No']);
	fwrite($fp, "\t");
	fwrite($fp, $ratio);
	fwrite($fp, "\t");
	fwrite($fp, implode(", ", array_keys($pic['types'])));
	fwrite($fp, "\n");
}
fclose($fp);

print_r($statistics['creender']['sets']['total']);
print_r($statistics['rc']);
// print_r(count($statistics["creender"]["pictures"]));
