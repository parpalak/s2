/*
originally written by paul sowden <paul@idontsmoke.co.uk> | http://idontsmoke.co.uk
modified and localized by alexander shurkayev <alshur@narod.ru> | http://htmlcoder.visions.ru
*/
// Modified by Roman Parpalak

window.onerror = null;

tooltip = {

	attr_name: "alt",
	max_width: 0,
	delay: 500,

	t: document.createElement("DIV"),
	c: null,
	g: false,

	m: function(e){
		if (tooltip.g){
			oCanvas = document.getElementsByTagName(
			(document.compatMode && document.compatMode == "CSS1Compat") ? "HTML" : "BODY"
			)[0];
			x = window.event ? event.clientX + oCanvas.scrollLeft : e.pageX;
			y = window.event ? event.clientY + oCanvas.scrollTop : e.pageY;
			tooltip.a(x, y);
		}
	},

	d: function(){
		tooltip.t.setAttribute("id", "tooltip");
		document.body.appendChild(tooltip.t);
		document.onmousemove = tooltip.m;
		window.onscroll = tooltip.h;
		tooltip.a(-99, -99);
	},
	
	_: function(s){
		s = s.replace(/\&/g,"&amp;");
		s = s.replace(/\</g,"&lt;");
		s = s.replace(/\>/g,"&gt;");
		return s;
	},

	s: function(e){
		d = window.event ? window.event.srcElement : e.target;
		if (!d.getAttribute(tooltip.attr_name)) return;
		s = d.getAttribute(tooltip.attr_name);

		if (tooltip.t.firstChild)
			tooltip.t.removeChild(tooltip.t.firstChild);
		tooltip.t.appendChild(document.createTextNode(s));

		tooltip.c = setTimeout("tooltip.t.style.visibility = 'visible';", tooltip.delay);
		tooltip.g = true;
	},

	h: function(e){
		tooltip.t.style.visibility = "hidden";
		if (tooltip.t.firstChild) tooltip.t.removeChild(tooltip.t.firstChild);
		clearTimeout(tooltip.c);
		tooltip.g = false;
		tooltip.a(-99, -99);
	},

	l: function(o, e, a){
		if (o.addEventListener) o.addEventListener(e, a, false);
		else if (o.attachEvent) o.attachEvent("on" + e, a);
			else return null;
	},

	a: function(x, y){
		oCanvas = document.getElementsByTagName(
		(document.compatMode && document.compatMode == "CSS1Compat") ? "HTML" : "BODY"
		)[0];
		
		w_width = oCanvas.clientWidth ? oCanvas.clientWidth + oCanvas.scrollLeft : window.innerWidth + window.pageXOffset;
		w_height = window.innerHeight ? window.innerHeight + window.pageYOffset : oCanvas.clientHeight + oCanvas.scrollTop; // should be vice verca since Opera 7 is crazy!

		tooltip.t.style.width = ((tooltip.max_width) && (tooltip.t.offsetWidth > tooltip.max_width)) ? tooltip.max_width + "px" : "auto";

		t_width = tooltip.t.offsetWidth;
		t_height = tooltip.t.offsetHeight;

		tooltip.t.style.left = x + 16 + "px";
		tooltip.t.style.top = y + 16 + "px";

		if (x + 16 + t_width > w_width)
			tooltip.t.style.left = w_width - t_width + "px";
		if (y + 16 + t_height > w_height)
			tooltip.t.style.top = w_height - t_height + "px";
	}
}

var root = window.addEventListener || window.attachEvent ? window : document.addEventListener ? document : null;
if (root){
	if (root.addEventListener)
		root.addEventListener("load", tooltip.d, false);
	else if (root.attachEvent)
		root.attachEvent("onload", tooltip.d);
}

function ShowTip (e)
{
	var eItem = window.event ? window.event.srcElement : e.target;

	if (eItem.nodeName == "IMG")
	{
		tooltip.s(e);
		window.status = eItem.getAttribute('alt');
	}
}

function HideTip (e)
{
	var eItem = window.event ? window.event.srcElement : e.target;

	if (eItem.nodeName == "IMG")
	{
		tooltip.h(e);
		window.status = window.defaultStatus;
	}
}