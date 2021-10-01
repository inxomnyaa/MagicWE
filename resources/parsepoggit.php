<?php

declare(strict_types=1);

$yaml = yaml_parse_file(".poggit.yml");

$urls = [];

foreach ($yaml["projects"] ?? [] as $project) {
	foreach ($project["libs"] ?? [] as $lib) {
		$src = $lib["src"] ?? null;
		if ($src === null) {
			echo "Missing src in libs";
			continue;
		}
		$version = $lib["version"] ?? "*";
		$branch = $lib["branch"] ?? ":default";

		$urls[] = "https://poggit.pmmp.io/v.dl/$src/$version?branch=$branch";
	}
}

print implode('
', $urls);