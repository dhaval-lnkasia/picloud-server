<?php if (OC_Util::getEditionString() === OC_Util::EDITION_COMMUNITY): ?>
	<p>
		<?php print_unescaped(\str_replace(
	[
		'{communityopen}',
		'{githubopen}',
		'{licenseopen}',
		'{linkclose}',
	],
	[
		'<a href="https://owncloud.com/contact-us" target="_blank" rel="noreferrer">',
		'<a href="https://github.com/owncloud" target="_blank" rel="noreferrer">',
		'<a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noreferrer">',
		'</a>',
	],
	$l->t('Developed by the LNKSIA TECHSOL, www.lnkasia.com')
)); ?>
	</p>
<?php endif; ?>
