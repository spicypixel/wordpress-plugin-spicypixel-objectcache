<?php if (!defined('SPOC')) die(); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" <?php do_action('admin_xml_ns'); ?> <?php language_attributes(); ?>>
	<head>
		<title>Error</title>
		<meta http-equiv="Content-Type" content="<?php bloginfo('html_type'); ?>; charset=<?php echo get_option('blog_charset'); ?>" />
	</head>
	<body>
		<p>
		    <?php echo "<strong>Spicy Pixel Object Cache Error:</strong> " . $error; ?>
		</p>
	</body>
</html>