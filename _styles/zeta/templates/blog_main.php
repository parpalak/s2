<?php if (!defined('S2_ROOT')) die; ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8" />
<title><!-- s2_head_title --></title>
<!-- s2_meta -->
<link rel="stylesheet" media="screen" href="<?php echo $GLOBALS['ext_info']['url']; ?>/style.css" />
<link rel="stylesheet" href="<?php echo S2_PATH.'/_styles/'.S2_STYLE ?>/site.css" />
<script src="<?php echo S2_PATH.'/_styles/'.S2_STYLE ?>/script.js"></script>
<!-- s2_styles -->
<!-- s2_navigation_link -->
</head>

<body class="blog_main">
	<div id="crumbs"><!-- s2_crumbs --></div>
<!-- s2_search_field -->
	<div id="header"><!-- s2_site_title --></div>

	<div id="center">
		<div id="container">
			<div id="content">
				<!-- s2_title -->
				<!-- s2_date -->
				<!-- s2_text -->
				<!-- s2_comment_form -->
			</div>
			<div id="menu">
				<!-- s2_blog_calendar -->
				<!-- s2_menu -->
				<!-- s2_blog_last_comments -->
			</div>
			<div class="clearing"></div>
		</div>
		<!-- s2_debug -->
	</div>

	<div id="footer">
		<p id="queries"><!-- s2_querytime --></p>
		<p id="copyright"><!-- s2_copyright --></p>
	</div>
</body>
</html>