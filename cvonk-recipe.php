<?php
//error_reporting(E_ALL);
/* 
   Plugin Name: Cvonk Recipes
   Plugin URI: https://coertvonk.com/download/wp/cvonk-recipes.tgz
   Version: v1.00
   Author: <a href="https://coertvonk.com/">Coert Vonk</a>
   Description: Plugin to display recipes from ERM (Electronic Recipe Manager)

   Abstract:
     Displays recipes.  The original recipes are kept using Electronic Recipe
     Manager (ERM) and stored in a MS Access database.  To make them more
     accessible they have been migrated to MySQL using the Migration Tool.

   Installation:
    1. Upload this plugin to your 'wp-content/plugins/' directory
    2. Activate the plugin through the 'plugins' menu in WordPress.  Look for
       the settings link to configure the database options.  Enter the 
       details for the recipe database.
    3. To display the recipies in your blog, add the shortcode
       '[cvonk-recipes]' in a page or post.  That will become your recipe
       page.  The first time, it will display an overview of the recipes.
       When a user clicks on one of the recipes, the same post/page will
       reload, but this time it will display the details of the selected
       recipe.

   Methodology:
     This plugin uses a class to reduce the change of namespace collisions.
     The class constructor: registers the shortcode; registers the admin
     panel; and registers a query_vars filter to prevent WordPress from
     filtering the GET parameters from the URL (as in http//www.vonk/family/
     cooking/recipes?id=12).

   Copyright:
     Copyright 2009-2018  Coert Vonk, All rights reserved

   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.

   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.

   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

if (!class_exists("CvonkRecipes")) {
    class CvonkRecipes {

	protected $basename;
	protected $slug = 'cvonk-recipes';
	protected $settings = array('host' => 'localhost',
				    'dbase' => 'recipes', 
				    'user' => '', 
				    'passwd' => '',
				    'permalink' => 'family/cooking/our-recipes-231' );
	
	protected $settingsDbaseName = "CvonkRecipesSettings";
	
	public function __construct() {
	    $this->basename = plugin_basename(__FILE__);
	    add_filter('query_vars', array(&$this, 'query_vars'));
	    add_shortcode('cvonk-recipes', array(&$this, 'shortcode'));
	    if (is_admin()) {
		add_action('admin_menu', array(&$this, 'settingsPage'));
	    }
	    //register_activation_hook( __FILE__, array(&$this, 'flush_rewrite_rules'));
	    $this->settings = $this->getSettings();
	    if ( isset( $this->settings['permalink'] ) ) {
		add_action('generate_rewrite_rules', array(&$this, 'generate_rewrite_rules'));
	    }
	}

	public function init() {  // called when plugin is activated
	    $this->getSettings();
	}

	public function query_vars( $vars ) {  // do not filter GET parameters
	    $vars[] = "id";
	    $vars[] = "sort";
	    return $vars;
	}

	public function pluginActionLinks($action_links) {
	    $settings_link = '<a href="plugins.php?page='.$this->basename.'">' . __('Settings') . '</a>';
	    array_unshift( $action_links, $settings_link );      
	    return $action_links;
	}    

	public function generate_rewrite_rules( $wp_rewrite ) {
	    $this->settings = $this->getSettings();
	    if ( isset ( $this->settings['permalink'] ) ) {
		$new_rules = array( $this->settings['permalink']. '/([0-9]+)$' =>
		    'index.php?name=our-recipes&'. 'id=$matches[1]'
		);
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	    }
	    return $wp_rewrite;
	}

	// permalinks need to be flushed after activation, so that above new
	// rules are added
	public function flush_rewrite_rules() {
	    $this->settings = $this->getSettings();
	    if ( isset ( $this->settings['permalink'] ) ) {
		add_action('generate_rewrite_rules', array(&$this, 'generate_rewrite_rules'));
	    }
	    global $wp_rewrite;
	    $wp_rewrite->flush_rules();
	}
	
	public function settingsPage() {
	    // http://codex.wordpress.org/Adding_Administration_Menus#Sub-Menus
	    if (current_user_can('manage_options')) {
		add_filter('plugin_action_links_' . $this->basename,
			   array(&$this, 'pluginActionLinks'));
		if (function_exists('add_submenu_page')) {
		    add_submenu_page('plugins.php',                   // parent
				     'cvonk Recipes Options',         // page_title
				     'cvonk Recipes Options',         // menu_title
				     'manage_options',                // capability req'ed
				     __FILE__,                        // file/handle
				     array(&$this, 'settingsPageDisplay'));  // function
		}
	    }
	}

	protected function getSettings() {
	    $dbase = get_option($this->settingsDbaseName);  // load from WP dbase
	    if (!empty($dbase)) {
		foreach ($dbase as $key => $option) {
		    $this->settings[$key] = $option;
		}
	    }
	    update_option($this->settingsDbaseName, $this->settings); // store in WP
	    return $this->settings;
	}

	private function inputChecked( $checked ) {
	    if ( $checked ) {
		return ' checked="checked"';
	    }
	}
	
	public function settingsPageDisplay() {
	    if ( function_exists('current_user_can') && 
		 !current_user_can('manage_options') ) {
		die('You do not have permissions for managing this option');
	    }

	    $prevsettings = $this->getSettings();      
	    if (isset($_POST['Settings'])) {
		foreach ($this->settings as $key => $value) {
		    if (isset($_POST[$key])) {
			$this->settings[$key] = $_POST[$key];
		    } else {
			if ($key == 'permalink') {
			    $this->settings[$key] = '';
			}
		    }
		}
		update_option($this->settingsDbaseName, $this->settings);

		$additional = "";
		if ($prevsettings['permalink'] != $this->settings['permalink'] ) {
		    global $wp_rewrite;
		    $this->flush_rewrite_rules();
		    $additional = "<br />Permalink structure updated";

		    // CJV Debugging
		    global $wp_rewrite; // Global WP_Rewrite class object
		    ;
		    foreach($wp_rewrite->rewrite_rules() as $key => $value ) {
			echo "[$key] => $value<br>";
		    }
		    /**/
		}
		echo "<div class='updated'><p><strong>Settings updated$additional</strong></p></div>";
	    }
?>
<div class="wrap">
    <form method="post" action="<?php echo $_SERVER["REQUEST_URI"]; ?>">
	<div id="icon-options-general" class="icon32"></div>
	<h2>Recipes Settings</h2>
	<p>To use this plugin, place the <code>[cvonk-recipes]</code> shortcode in a post or page</p>
	<h3>Database host</h3>
	<table>
	    <tr>
		<td>Host name</td>
		<td><input name="host" type="text" size="35" value="<?php echo $this->settings['host'] ?>" /></td>
	    </tr><tr>
		<td>Database name</td>
		<td><input name="dbase" type="text" size="35" value="<?php echo $this->settings['dbase'] ?>" /></td>
	    </tr><tr>
		<td>User name</td>
		<td><input name="user" type="text" size="35" value="<?php echo $this->settings['user'] ?>" /></td>
	    </tr><tr>
		<td>Password</td>
		<td><input name="passwd" type="password" size="35" value="<?php echo $this->settings['passwd'] ?>" /></td>
	    </tr><tr>
		<td>Permalink prefix</td>
		<td><input name="permalink" type="text" size="35" value="<?php echo $this->setting['permalink'] ?>" /> I.e. 'family/cooking/our-recipes-231'.  Leave blank to not use permalinks.</td>
	    </tr><tr>
		<td colspan='2'>
		    <div class="submit">
			<input class="button-primary" type="submit" name="<?php echo "Settings" ?>" value="<?php _e('Save Options') ?>" />
		    </div>
	    </tr>
	</table>
    </form>
</div>
<?php
} // end function settingsPageDisplay()

protected function isEmpty($par) {
    return ($par == "" or $par == "---" or $par == "00:00" );
}

protected function generateSearchBoxAndList($link) {
    // show a search box
    
    $search = array( "title" => "",
		     "ingredient" => "" );
    $matching = "";
    if (isset($_POST['search'])) {
	$matching = "matching search criteria";
	foreach ($search as $key => $value) {
	    if (isset($_POST[$key])) {
		    $search[$key] = mysqli_real_escape_string($link, $_POST[$key]);
	    }
	}
    }
    
    $result =
	    "
<P>The vast majority of our 400+ recipes are vegetarian. Most are adjusted to match the ingredients available in our area.</P>
<style type='text/css'>
.cvonkrecipes table {
  background: #eee;
  width: 100%;
}
.cvonkrecipes table tbody tr td input {
	width: 100%;
}
.cvonkrecipes fieldset { 
  font: 80%/1 sans-serif;
}
.cvonkrecipes label {
  float: left;
  //width: 25%;
  margin-right :0.5em;
  padding-top: 0.3em;
  text-align: right;
  font-weight: bold;
}
.cvonkrecipes legend {
  text-align:right;
  margin-bottom: 0;
}
</style>
<form method='post' action='/family/cooking/our-recipes-231' width='100%'>
 <fieldset class='cvonkrecipes'>
  <legend>Search for recipes containing</legend>
  <table>
   <tbody>\n";

	foreach ($search as $key => $value) {
	    $result .= "   <tr>
	 <td><label for='$key'>". ucwords($key). "</label></td>
	 <td><input name='{$key}' type='text' value='{$value}' onfocus=this.value=''></input></td>
    </tr>";
	}

	$result .= "
     <tr><td></td>
     <td><input type='submit' name='search' value='Search'></td>
    </tr>
   </tbody>
  </table> 
 </fieldset>
</form>";

    // enough, time to show the table
    // display all the recipe titles, when no specific recipe was specified

    if ( $sort == "" ) {
	$sort = "title";
    }
    
    // Query SQL database (search on title and/or ingredient when specified)
    if ( $search['title'] != "" or $search['ingredient'] != "" ) {
	$query = 
	    "SELECT DISTINCT r.* ".
	    "FROM recipe r, ingredients i ".
	    "WHERE ( r.title LIKE '%{$search['title']}%' AND ".
	    "      ( i.cabinet LIKE '%{$search['ingredient']}%' AND i.recipe_id = r.ID ) )".
	    "ORDER BY r.$sort;";
    } else {
	$query = "SELECT r.* FROM recipe r ORDER BY r.$sort";
    }
    $queryresult = mysqli_query( $link, $query ) or die(mysql_error());
    
    $result .= "<h2 class='nocount'>Recipes {$matching}</h2>\n";
    $result .= "<table class='zebra' border='0'>\n";
    $result .= "<tr><th><a href='?sort=title'>Title</a></th>".
	       "<th><a href='?sort=cuisine'>Cuisine</a></th>".
	       "<th><a href='?sort=category'>Category</a></th></tr>\n";
    while ($row = mysqli_fetch_array($queryresult, MYSQLI_ASSOC)) {
	if ( isset( $this->settings['permalink'] ) ) {
	    $result .= "<tr><td>".
		       "<a href='/". $this->settings['permalink']. "?id=". $row[ID]. "'>". 
		       iconv("CP1252", "UTF-8", $row['title']). 
		       "</a></td><td>$row[cuisine]</td><td>$row[category]</td></tr>\n";
	} else {
	    $result .= "<tr><td>".
		       "<a href='?id=$row[ID]'>". 
		       iconv("CP1252", "UTF-8", $row['title']). 
		       "</a></td><td>$row[cuisine]</td><td>$row[category]</td></tr>\n";
	}
    }
    $result .= "</table>\n";
    return $result;
}

protected function durationToISO8601($duration)
{
    list($hours,$mins) = explode(':',$duration);
    return "PT" . $hours . "H" . $mins . "M";
}

protected function dailyValue($nutrition, $key)
{
    $notEstablished = 0;
    $dv = [ "Calories"        => 2000,
	    "Fat"             => 65,
	    "Trans Fat"       => $notEstablished,
	    "Saturated Fat"   => 20,
	    "Unsaturated Fat" => $notEstablished,
	    "Cholesterol"     => 300,
	    "Sodium"          => 2400,
	    "Carbohydrates"   => 300,
	    "Fiber"           => 25,
	    "Sugars"          => $notEstablished,
	    "Protein"         => $notEstablished ];
    $html = "";
    if (!$this->isEmpty($nutrition[$key])) {
	$html = round(100 * (float)$nutrition[$key] / $dv[$key]) . "%";
    }
    return $html;
}

protected function showNutritionFacts($nutrition)
{
    
    $html  = "<div class='align-center'>
  <figure>
    <section class='nutrition-facts'>
      <header>
        <h1>Nutrition Facts</h1>
        <p>" . $nutrition['servings'] . " per recipe</p>
        <p><strong>Serving size <span>" . $nutrition["ServingSize"] . "</span></strong></p>
      </header>
      <table class='main-nutrients'>
        <thead>
          <tr>
            <th colspan='4'>Amount per serving <br /><strong>Calories</strong><span>" . $nutrition["Calories"] . "</span></th>
          </tr>
        </thead>
        <tbody>
          <tr class='daily-value'>
            <th colspan='4'><strong>% Daily Value*</strong></th>
          </tr>
          <tr class='br'>
            <th class='fat' colspan='3'><strong>Total Fat</strong> " . $nutrition["Fat"] . "</th>
            <td>" . $this->dailyValue($nutrition, "Fat") . "</td>
          </tr>
          <tr>
            <td></td><th colspan='2'>Saturated Fat " . $nutrition["Saturated Fat"] . "</th>
            <td>" . $this->dailyValue($nutrition, "Saturated Fat") . "</td>
          </tr>
          <tr>
            <td></td><th colspan='2'><em>Trans</em> Fat " . $nutrition["Trans Fat"] . "</th>
            <td></td>
          </tr>
          <tr>
            <th colspan='3'><strong>Cholesterol</strong> " . $nutrition["Cholesterol"] . "</th>
            <td>" . $this->dailyValue($nutrition, "Cholesterol") . "</td>
          </tr>
          <tr>
            <th colspan='3'><strong>Sodium</strong> " . $nutrition["Sodium"] . "</th>
            <td>" . $this->dailyValue($nutrition, "Sodium") . "</td>
          </tr>
          <tr>
            <th colspan='3'><strong>Total Carbohydrate</strong> " . $nutrition["Carbohydrates"] . "</th>
            <td>" . $this->dailyValue($nutrition, "Carbohydrates") . "</td>
          </tr>
          <tr>
            <td></td>
            <th colspan='2'>Dietary Fiber " . $nutrition["Fiber"] . "</th>
            <td>" . $this->dailyValue($nutrition, "Fiber") . "</td>
          </tr>
          <tr>
            <td></td>
            <th colspan='2'>Total Sugars " . $nutrition["Sugars"] . "</th>
            <td></td>
          </tr>
          <tr>
            <td class='indent'></td><td class='indent'></td>
            <th>Includes " . $nutrition["Added Sugars"] . "  Added Sugars</th>
            <td>" . $this->dailyValue($nutrition, "Sugars") . "</td>
          </tr>
          <tr>
            <th colspan='3'><strong>Protein</strong> " . $nutrition["Protein"] . "</th>
            <td></td>
          </tr>
        </tbody>
      </table>
      <p class='footnote'>The % Daily Value (DV) tells you how much a nutrient in a serving of food " .
	     "contributes to a daily diet. 2,000 calories a day is used for general nutrition advice.</p>
      </section>
  </figure>
</div>
";
    
    return $html;
}


// generate HTML and JSON for the recipe from SQL $link identified by $id

protected function showRecipe($link, $id) {

    // get recipe from SQL

    $query = "SELECT r.* FROM recipe r WHERE ID=$id";
    $recipe = mysqli_query($link, $query) or die(mysql_error());
    $row = mysqli_fetch_array($recipe, MYSQLI_ASSOC);

    // show recipe

    $nutition = [];
    $html = "<h2 id='start' class='nocount'>". iconv("CP1252", "UTF-8", $row['title']). "</h2>\n";
    $imagefile = $row["title"] . ".jpg";
    $imagefile = str_replace('w/', 'with', $imagefile);
    $imagepath = get_home_path() . "cvonk/recipe-images/" . $imagefile;
    $imagefile = str_replace(' ', '%20', $imagefile);
    $imagefile = str_replace("'", '%27', $imagefile);
    $imagefile = str_replace('#', '%23', $imagefile);
    $imageurl = get_home_url(null, "/cvonk/recipe-images/" . $imagefile);

    $json = [ "@context" => "http://schema.org/",
	      "@type" => "Recipe",
	      "name" => iconv("CP1252", "UTF-8", $row['title']) ];
    if (is_readable($imagepath)) {
	$html .= "<div class='align-center'>\n  <figure>\n    <a class='hide-anchor' href='{$imageurl}'>" .
		 "<img width='220' src='{$imageurl}' alt='' class='alignnone size-full' /></a>\n" .
		 "    <figcaption>{$row['title']}</figcaption>\n  </figure>\n</div>\n";
	$json["image"] = $imageurl;
    }
    if (!$this->isEmpty($row['notes'])) {
	$html .= "<p>". iconv("CP1252", "UTF-8", $row['notes']). "</p>\n";
	$json["description"] = iconv("CP1252", "UTF-8", $row['notes']);
    } else {
	$json["description"] = iconv("CP1252", "UTF-8", $row['title']);
    }

    $html .= "<table border='0'>\n";
    $sqlToJsonDict = [ "source" => "author", "prep_time" => "prepTime", "cook_time" => "cookTime",
		       "servings" => "recipeYield", "rating" => "aggregateRating" ];
    // sources that inspired recipes
    $inspiration = ["Bon Appetit", "Cooking Light", "Eat, Drink and Be Healthy", "Field of greens", "Gourmet",
		    "allrecipes.com", "epicurious.com", "fatsforhealth.com", "hermans.org", "hollandsepot.dordt.nl",
		    "weightwatchers.com", "wereldexpat.nl", "wereldsint.nl", "Jane Brody", "joyofbaking.com",
		    "Moosewood", "New York Times", "news://rec.food.recipes", "Oregonian", "Oxmoor House",
		    "Papa Hayden", "Southern Living", "Sunset", "Tassajara", "Bread Machine Book",
		    "The Silver Palate", "Vegetarian Cooking", "Vegetarian Times", "Waring", "Weight Watchers",
		    "Williams Sonoma"];

    foreach( $sqlToJsonDict as $sqlKey => $jsonKey ) {
	switch($sqlKey) {
	    case "source":
		if( !$this->isEmpty($row[$sqlKey]) ) {
		    $name = iconv("CP1252", "UTF-8", $row[$sqlKey]);

		    foreach( $inspiration as $publication ) {
			if (strpos($name, $publication) === 0) {  // if name starts with $publication
			    $name = "Inspired by " . $name;
			    break;
			}
		    }
		    $html .= "  <tr><td>". ucwords($sqlKey). ":</td><td>". $name . "</td></tr>\n";
		    $json[$jsonKey] = [ "@type" => "Person", "name" => $row[$sqlKey] ];
		}
		break;
	    case "prep_time":
	    case "cook_time":
		if (!$this->isEmpty($row[$sqlKey])) {
		    $html .= "  <tr><td>". ucwords($sqlKey). ":</td><td>".
			     iconv("CP1252", "UTF-8", $row[$sqlKey]). "</td></tr>\n";
		    $json[$jsonKey] = $this->durationToISO8601($row[$sqlKey]);
		}
		break;
	    case "rating":
		if (!$this->isEmpty($row[$sqlKey])) {
		    $html .= "  <tr><td>". ucwords($sqlKey). ":</td><td>".
			     iconv("CP1252", "UTF-8", $row[$sqlKey]). "</td></tr>\n";
		    $json["aggregateRating"] = [ "@type" => "AggregateRating",
						 "ratingValue" => strlen($row[$sqlKey]),
						 "reviewCount" => 2 ];
		}
		break;
	    case "servings":
		$nutrition[$sqlKey] = $row[$sqlKey];
		// fall through to default
	    default:
		if (!$this->isEmpty($row[$sqlKey])) {
		    $html .= "  <tr><td>". ucwords($sqlKey). ":</td><td>".
			     iconv("CP1252", "UTF-8", $row[$sqlKey]). "</td></tr>\n";
		    $json[$jsonKey] = iconv("CP1252", "UTF-8", $row[$sqlKey]);
		}
	}
    }
    $html .= "</table>\n";

    // get recipe ingredients from SQL
    
    $query = "SELECT * FROM ingredients i WHERE recipe_id = $id";
    $queryresult = mysqli_query( $link, $query ) or die(mysql_error());

    // show recipe ingredients
    
    $html .= "<h3 class='nocount'>Ingredients</h3>\n";
    $html .= "<table border='0'>\n";
    while ($row = mysqli_fetch_array($queryresult, MYSQLI_ASSOC)) {
	if ( ! $this->isEmpty( $row['amount'] ) ) {
	    $amount = $row['amount'];
	} else {
	    $amount = "";
	}
	if ( ! $this->isEmpty( $row['measure'] ) ) {
	    $amount .= " ". $row['measure'];
	}
	$html .= "  <tr><td>". $amount. "</td>".
		 "<td>". iconv("CP1252", "UTF-8", $row['ingredient']) . "</td></tr>\n";
	
	$json["recipeIngredient"][] = $amount . " " . iconv("CP1252", "UTF-8", $row['ingredient']);
    }
    $html .= "</table>\n";
    
    // get recipe directions from SQL

    $query = "SELECT * FROM directions WHERE recipe_id = $id";
    $queryresult = mysqli_query( $link, $query ) or die(mysql_error());

    // show recipe directions
    
    $html .= "<h3 class='nocount'>Directions</h3>\n";
    $html .= "<table border='0'>\n";
    while ($row = mysqli_fetch_array($queryresult, MYSQLI_ASSOC)) {
	$direction = iconv("CP1252", "UTF-8", $row['direction']);
	$html .= "  <tr><td valign='top'>" . $row['step'] . ".</td><td>". $direction . "</td></tr>\n";
	$json["recipeInstructions"][] = $row["step"] . ". " . $direction;
    }
    $html .= "</table>\n";

    // get recipe nutrition facts from SQL

    $query = "SELECT * FROM nutrition WHERE recipe_id = $id";
    $queryresult = mysqli_query( $link, $query ) or die(mysql_error());
    $row = mysqli_fetch_array($queryresult, MYSQLI_ASSOC);
    $nutrition["ServingSize"] = $row["portion"];
    
    $query = "SELECT * FROM nutrients WHERE recipe_id = $id";
    $queryresult = mysqli_query( $link, $query ) or die(mysql_error());

    // show recipe nutrition facts

    $sqlToJsonDict = [ "Servings"        => [ "servingSize",           "" ],
		       "Calories"        => [ "calories",              "" ],
		       "Fat"             => [ "fatContent",            "g" ],
		       "Saturated Fat"   => [ "saturatedFatContent",   "g" ],
		       "Unsaturated Fat" => [ "unsaturatedFatContent", "g" ],
		       "Cholesterol"     => [ "cholesterolContent",    "mg" ],
		       "Sodium"          => [ "sodiumContent",         "mg" ],
		       "Carbohydrates"   => [ "carbohydrateContent",   "g" ],
		       "Fiber"           => [ "fiberContent",          "g" ],
		       "Protein"         => [ "proteinContent",        "g" ] ];
    $count = 0;
    while ($row = mysqli_fetch_array($queryresult, MYSQLI_ASSOC)) {
	if($count === 0) {
	    $json["nutrition"]["@type"] = "NutritionInformation";
	    if ($this->isEmpty($nutrition["ServingSize"])) {
		$nutrition["ServingSize"] = "1 serving";
	    }
	    $json["nutrition"]["servingSize"] = $nutrition["ServingSize"];
	}
	$sqlKey = $row['nutrient'];
	$jsonKey = $sqlToJsonDict[$sqlKey][0];
	$unit = $sqlToJsonDict[$sqlKey][1];
	$amount = iconv("CP1252", "UTF-8", $row['amount']);
	$nutrition[$sqlKey] = $amount . $unit;
	$json["nutrition"][$jsonKey] = $amount;
	$count++;
    }
    if ($count) {
	$html .= "<h3 class='nocount'>Nutrition</h3>\n";
	$html .= $this->showNutritionFacts($nutrition);
    }    
    $json_str  = "\n<script type='application/ld+json'>\n";
    $json_str .= json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);    
    $json_str .= "\n</script>\n";
    return $json_str . $html;
}

public function shortcode($atts) {
    
    $result = "";

    // attributes such as "id" or "sort" originate from
    // - the URL where they are passed using GET params, if not there use
    // - the attributes specified in the short code, if not there, use
    // - the defaults specified in the following array

    $atts = shortcode_atts( array ( "id" => "",
				    "sort" => "title" ), $atts );

    global $wp_query, $wp_rewrite;
    $this->settings = $this->getSettings();

    //
    // connect to the MySQL database
    //

    if (!extension_loaded('mysql')) {
	$pre = (PHP_SHLIB_SUFFIX === 'dll') ? 'php_' : '';
	dl($pre . 'mysql.' . PHP_SHLIB_SUFFIX);
    }
    
    $settings = $this->getSettings();
    
    $link = mysqli_connect($settings['host'],
			   $settings['user'],
			   $settings['passwd']) or die(mysql_error());
    mysqli_select_db($link, $settings['dbase']) or die(mysql_error());
    
    $arg = strtok( "id". ",".
		   "sort", ",");

    //echo "SORRY, catching up with WordPress changes.  Recipes are offline until further notice<br>";
    while ( $arg != false ) {
	$value = "";
        if ( $wp_rewrite->using_permalinks() &&
             isset( $this->settings['permalink'] ) ) {
            $value = get_query_var( $arg );
        } else {
            $value = $_GET[$arg];
        }
	if ( isset( $value) ) {
	    $value = mysqli_real_escape_string($link, stripslashes(wp_specialchars_decode( $value) ));
	    $atts[$arg] = preg_replace('/^\'(.*)\'$/', '${1}', $value);
        }
	$arg = strtok(",");
    }
    
    extract( $atts );  // convert to individual params

    // display the selected recipes.  if none is selected (no $id), display an
    // overview filtered by the search query

    if ( ! is_numeric( $id ) ) {
	$result .= "<h2 class='nocount'>Search</h2>\n";
	$result .= $this->generateSearchBoxAndList($link);	
    } else {
	$result .= $this->showRecipe($link, $id);
	
    }
    $result .= "<p />";
    return $result;
}
}
} // end Class CvonkRecipes


if (class_exists("CvonkRecipes")) {
    $cvonkRecipes_inst = new CvonkRecipes();
}

add_action('activate_cvonk-recipes/cvonk-recipes.php',
	   array(&$cvonkRecipes_inst, 'init'));

?>
