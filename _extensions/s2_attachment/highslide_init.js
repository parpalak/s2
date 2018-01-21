hs.align = 'center';
hs.transitions = ['expand', 'crossfade'];
hs.outlineType = 'rounded-white';
hs.fadeInOut = true;

var s2_attachment_pictures = {
	slideshowGroup: 'pictures',
};

hs.addSlideshow({
	slideshowGroup: 'pictures',
	interval: 5000,
	repeat: false,
	useControls: true,
	fixedControls: 'fit',
	overlayOptions: {
		opacity: 0.75,
		position: 'bottom center',
		hideOnMouseOut: true
	}
});


hs.marginBottom = 105;

var s2_attachment_gallery = {
	dimmingOpacity: 0.75,
	slideshowGroup: 'gallery',
};

hs.addSlideshow({
	slideshowGroup: 'gallery',
	interval: 5000,
	repeat: false,
	useControls: true,
	fixedControls: false,
	overlayOptions: {
		className: 'text-controls',
		opacity: '1',
		position: 'bottom center',
		offsetX: '0',
		offsetY: '-60',
		relativeTo: 'viewport',
		hideOnMouseOut: false
	},
	thumbstrip: {
		mode: 'horizontal',
		position: 'bottom center',
		relativeTo: 'viewport'
	}

});
