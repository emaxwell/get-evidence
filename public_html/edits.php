<?php

include "lib/setup.php";

if ($_GET["oid"]) {
  $report_title = "Edit history";
  $where_sql = "edit_oid=?";
  $where_param = array($_GET["oid"]);
}
else if ($_GET["variant_id"]) {
  $report_title = "Edit history";
  $where_sql = "variant_id=?";
  $where_param = array($_GET["variant_id"] + 0);
}
else {
  $report_title = "Recent changes";
  $where_sql = "1=1";
  $where_param = array();
}

if (isset ($_GET["before_edit_id"]))
  $where_sql .= " AND edit_id < " . (0 + $_GET["before_edit_id"]);

$gOut["title"] = "Evidence Base: $report_title";

function print_content($x)
{
  global $report_title, $where_sql, $where_param;
  if (!$_GET["bareli"])
    print "<h1>$report_title</h1>\n\n";

  $sql_limit = $_GET["all"] ? "" : "LIMIT 300";

  $q = theDb()->query ("SELECT *, diseases.disease_name disease_name, edits.genome_id genome_id, UNIX_TIMESTAMP(edit_timestamp) t FROM edits
	LEFT JOIN eb_users ON edit_oid=oid
	LEFT JOIN variants ON variants.variant_id=edits.variant_id
	LEFT JOIN genomes ON edits.genome_id>0 AND edits.genome_id=genomes.genome_id
	LEFT JOIN diseases ON diseases.disease_id=edits.disease_id
	WHERE $where_sql AND is_draft=0
	ORDER BY edit_timestamp DESC
	$sql_limit",
		       $where_param);
  if (theDb()->isError ($q)) die ($q->getMessage());

  $relevant_fields = array ("is_delete", "variant_id",
			    "article_pmid", "genome_id", "disease_id",
			    "edit_oid");
  $lastrow = FALSE;
  $output_count = 0;

  if (!$_GET["bareli"])
    print "<UL>";
  while ($row =& $q->fetchRow()) {
    if ($lastrow &&
	arrays_partially_equal ($lastrow, $row, $relevant_fields) &&
	$lastrow["previous_edit_id"] > 0 == $row["previous_edit_id"] > 0)
      continue;

    $lastrow = $row;

    print "<LI>";

    print strftime ("%b %e ", $row["t"]);

    print "<A href=\"$row[variant_gene]-$row[variant_aa_from]$row[variant_aa_pos]$row[variant_aa_to];$row[edit_id]\">$row[variant_gene] ".aa_short_form("$row[variant_aa_from]$row[variant_aa_pos]$row[variant_aa_to]")."</A>";

    if ($row["is_delete"] && !$row["article_pmid"] && !$row["genome_id"] && !$row["disease_id"])
      print " deleted by ";
    else if (!$row["previous_edit_id"] && !$row["article_pmid"] && !$row["genome_id"])
      print " added by ";
    else
      print " edited by ";

    print ("<A href=\"edits?oid=".urlencode($row["edit_oid"])."\">".
	   (htmlspecialchars ($row["fullname"] ? $row["fullname"] : $row["nickname"])).
	   "</A>");

    if ($row["genome_id"])
      if (!($genome_name = $row["name"]))
	if (!($genome_name = $row["global_human_id"]))
	  $genome_name = "#".$row["genome_id"];
    $genome_name = htmlspecialchars ($genome_name);

    if ($row["article_pmid"] && $row["is_delete"])
      print " (PMID $row[article_pmid] removed)";
    else if ($row["genome_id"] && $row["is_delete"])
      print " ($genome_name removed)";
    else if ($row["article_pmid"] && !$row["previous_edit_id"])
      print " (PMID $row[article_pmid] added)";
    else if ($row["genome_id"] && !$row["previous_edit_id"])
      print " ($genome_name added)";
    else if ($row["disease_id"])
      print " (".htmlspecialchars ($row["disease_name"]).")";
    print "</LI>\n";

    if (!$_GET["all"] && ++$output_count >= 30) {
      print "<SPAN>&nbsp;<BR />\n";
      foreach (array ("", "&all=1") as $all) {
	print "<BUTTON onclick=\"\$('busysignal').removeClassName('csshide'); this.disabled = true; new Ajax.Updater (this.parentNode, 'edits?"
	  . htmlentities (ereg_replace ('&?(before_edit_id|bareli)=[0-9]+', '',
					$_SERVER[QUERY_STRING]))
	  . "&before_edit_id={$row[edit_id]}&bareli=1{$all}', { onFailure: function() { this.disabled = false; \$('busysignal').addClassName('csshide'); } }); return false;\">"
	  . ($all == "" ? "Next page" : "Show all")
	  . "</BUTTON> &nbsp; \n";
      }
      print " <IMG style=\"vertical-align: middle;\" id=\"busysignal\" class=\"csshide\" src=\"/img/busy.gif\" width=\"16\" height=\"16\" alt=\"\" /></SPAN>\n";
      break;
    }
  }
  if (!$_GET["bareli"])
    print "</UL>\n";
}

if ($_GET["bareli"])
  print_content ("");
else
  go();

?>
