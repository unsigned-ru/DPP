<?php

/* Sanity checks */
system("sudo apt-get install graphviz");
system("sudo git clone \"https://".getenv("GITHUB_TOKEN")."@github.com/brainboxdotcc/dpp-web.git\" /dpp-web");
system("sudo git clone --recursive https://github.com/brainboxdotcc/DPP.git " . getenv("HOME") . "/D++");

system("sudo cp /dpp-web/doxygen /usr/local/bin/doxygen && sudo chmod ugo+x /usr/local/bin/doxygen");

/* Make drop down list of versions from the tags */
echo "Make version drop down select\n";
system("git fetch -av --tags");
$tags = explode("\n", shell_exec("git tag"));
for ($n = 0; $n < count($tags); ++$n) {
	$tags[$n] = preg_replace('/^v/', '', $tags[$n]);
}
natsort($tags);
$tags = array_reverse($tags);

$opts = "<option value='/'>master</option>";
foreach ($tags as $tag) {
	if ($tag != '') {
		$opts .= "<option value='/$tag/'>$tag</option>";
	}
}

$taglist = '';
foreach ($tags as $tag) {
	if ($tag != '') {
		$tag2 = str_replace("v", "", $tag);
		$taglist .= "<a href='/".$tag2."/'>D++ Library version $tag</a>";
	}
}

$template = file_get_contents("header.template.html");
$header = str_replace("##VERSION_OPTIONS##", $opts, $template);

$footer = file_get_contents("footer.template.html");
$footer = str_replace("###PREV###", $taglist, $footer);

file_put_contents("header.html", $header);
file_put_contents("footer.html", $footer);

echo "Generate `master` docs\n";

chdir("..");
shell_exec("/usr/local/bin/doxygen");
system("sudo cp -r docs/* /dpp-web/");

/* Create old version docs */
chdir(getenv("HOME") . "/D++");
system("rm -rf " . sys_get_temp_dir() . "/dpp-old");
mkdir(sys_get_temp_dir() . "/dpp-old");
chdir(sys_get_temp_dir() . "/dpp-old");
foreach ($tags as $tag) {
	$orig_tag = $tag;
	$tag = preg_replace("/^v/", "", $tag);
	if (!empty($tag)) {
		print "Generate $orig_tag docs (https://dpp.dev/$tag/)\n";
		system("git clone --recursive https://github.com/brainboxdotcc/DPP.git");
		chdir("DPP");
		system("git checkout tags/$orig_tag");
		/* Older versions of the docs before 9.0.7 don't have these. Force them into the tree so old versions get current styling */
		system("cp -rv " . getenv("HOME") . "/D++/docpages/images docpages");
		system("cp -rv " . getenv("HOME") . "/D++/docpages/style.css docpages/style.css");
		system("cp -rv " . getenv("HOME") . "/D++/docpages/*.html docpages/");
		system("cp -rv " . getenv("HOME") . "/D++/doxygen-awesome-css doxygen-awesome-css");
		/* Always make sure that the version is using the latest doxygen,
		 * but rewrite version number (project number)
		 */
		$doxy = file_get_contents(getenv("HOME") . "/D++/Doxyfile");
		$doxy = str_replace("PROJECT_NUMBER         =", "PROJECT_NUMBER         = $tag", $doxy);
		file_put_contents("Doxyfile", $doxy);
		/* Rewrite selected version number so that each page has a new default selected in the drop down */
		$hdr = file_get_contents(getenv("HOME") . "/D++/docpages/header.html");
		$hdr = str_replace("option value='/$tag/'", "option selected value='/$tag/'", $hdr);
		/* Rewrite version info in header */
		file_put_contents("docpages/header.html", $hdr);		
		shell_exec("/usr/local/bin/doxygen");
		system("sudo mkdir /dpp-web/$tag");
		system("sudo cp -r docs/* /dpp-web/$tag");
		chdir("..");
		system("rm -rf " . sys_get_temp_dir() . "/dpp-old/DPP");
	}
}

/* Commit and push everything to the github pages repo */
echo "Commit and push\n";
chdir("/dpp-web");
#system("git add -A");
#system("git commit -a -m \"automatic commit\"");
#system("git push");

