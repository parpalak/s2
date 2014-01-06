(function ()
{
	// Saving comments to localstorage
	function initStorage ()
	{
		var frm = document.forms['post_comment'];
		frm = frm && frm.elements;

		function save ()
		{
			var text = frm['Message'].value;
			if (text)
				localStorage.setItem('comment_text_' + document.location.pathname, text);
			else
				localStorage.removeItem('comment_text_' + document.location.pathname);
			localStorage.setItem('comment_name', frm['Name'].value);
			localStorage.setItem('comment_email', frm['Email'].value);
			localStorage.setItem('comment_showemail', frm['ShowEmail'].checked + 0);
		}

		try
		{
			if (!('localStorage' in window) || window['localStorage'] === null)
				return;

			if (document.cookie.indexOf('comment_form_sent=1') != -1)
			{
				document.cookie = 'comment_form_sent=0; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/';
				var eLink = document.getElementById('back_to_commented'),
					path = ((!frm && eLink) ? eLink : document.location).pathname;

				localStorage.removeItem('comment_text_' + path);
			}
			else
				frm['Message'].value = frm['Message'].value || localStorage.getItem('comment_text_' + document.location.pathname) || '';

			if (!frm)
				return;

			if (bIE)
			{
				frm['Name'].attachEvent('onchange', save);
				frm['Email'].attachEvent('onchange', save);
				frm['ShowEmail'].attachEvent('onchange', save);
				frm['Message'].attachEvent('onchange', save);
				document.forms['post_comment'].attachEvent('onsubmit', save);
			}
			if (bW3)
			{
				frm['Name'].addEventListener('change', save, false);
				frm['Email'].addEventListener('change', save, false);
				frm['ShowEmail'].addEventListener('change', save, false);
				frm['Message'].addEventListener('change', save, false);
				document.forms['post_comment'].addEventListener('submit', save, false);
			}

			frm['Name'].value = frm['Name'].value || localStorage.getItem('comment_name');
			frm['Email'].value = frm['Email'].value || localStorage.getItem('comment_email');
			frm['ShowEmail'].checked = frm['ShowEmail'].checked || !!(localStorage.getItem('comment_showemail') - 0);

			save();
			setInterval(save, 5000);
		}
		catch (e) {}
	}

	// Ctrl + arrows navigation
	var inpFocused = false, upLink, nextLink, prevLink;

	function focus ()
	{
		inpFocused = true;
	}

	function blur ()
	{
		inpFocused = false;
	}

	function navigateThrough (e)
	{
		e = e || window.event;

		if (!(e.ctrlKey || e.altKey) || inpFocused)
			return;

		var link = false;
		switch (e.keyCode ? e.keyCode : e.which ? e.which : null)
		{
			case 0x27:
				link = nextLink;
				break;
			case 0x25:
				link = prevLink;
				break;
			case 0x26:
				link = upLink;
				break;
		}

		if (link)
		{
			document.location = link;
			if (window.event)
				window.event.returnValue = false;
			if (e.preventDefault)
				e.preventDefault();
		}
	}

	function initNavigate ()
	{
		if (!document.getElementsByTagName)
			return;

		var e, i, ae = document.getElementsByTagName('LINK');
		for (i = ae.length; i-- ;)
		{
			e = ae[i];
			if (e.rel == 'next')
				nextLink = e.href;
			if (e.rel == 'prev')
				prevLink = e.href;
			if (e.rel == 'up')
				upLink = e.href;
		}

		if (bIE)
		{
			document.attachEvent('onkeydown', navigateThrough);

			ae = document.getElementsByTagName('INPUT');
			for (i = ae.length; i-- ;)
			{
				e = ae[i];
				if (e.type == 'text' || e.type == 'password' || e.type == 'search')
				{
					e.attachEvent('onfocus', focus);
					e.attachEvent('onblur', blur);
				}
			}

			ae = document.getElementsByTagName('TEXTAREA');
			for (i = ae.length; i-- ;)
			{
				e = ae[i];
				e.attachEvent('onfocus', focus);
				e.attachEvent('onblur', blur);
			}
		}
		if (bW3)
		{
			document.addEventListener('keydown', navigateThrough, true);

			ae = document.getElementsByTagName('INPUT');
			for (i = ae.length; i-- ;)
			{
				e = ae[i];
				if (e.type == 'text' || e.type == 'password' || e.type == 'search')
				{
					e.addEventListener('focus', focus, true);
					e.addEventListener('blur', blur, true);
				}
			}

			ae = document.getElementsByTagName('TEXTAREA');
			for (i = ae.length; i-- ;)
			{
				e = ae[i];
				e.addEventListener('focus', focus, true);
				e.addEventListener('blur', blur, true);
			}
		}
	}


	function Init()
	{
		if (started)
			return;
		started = true;

		initNavigate();
		initStorage();
	}

	var bW3 = document.addEventListener != null,
		bIE = !bW3 && document.attachEvent != null,
		started = false;

	if (bIE)
		attachEvent('onload', Init);
	if (bW3)
	{
		document.addEventListener('DOMContentLoaded', Init, false);
		addEventListener('load', Init, true);
	}

})();
