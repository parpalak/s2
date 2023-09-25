<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:xsl="http://www.w3.org/1999/XSL/Transform">
    <xsl:template match="/">
        <html>
            <head>
                <title><xsl:value-of select="rss/channel/title" /></title>
                <style type="text/css">
                    a {
                        color: #56d;
                        text-decoration-thickness: 1px;
                        text-decoration-color: rgba(85, 102, 221, 0.5);
                    }
                    body {
                        max-width: 720px;
                        font: 16px/1.5 sans-serif;
                        margin: 0 auto;
                    }
                    h1, h2, h3 {
                        margin: 1em 0 0.25em;
                    }
                    p {
                        margin: 0 0 0.75em;
                    }
                </style>
            </head>
            <body>
                <h1>
                    <a href="{rss/channel/link}">
                        <xsl:value-of select="rss/channel/title" />
                    </a>
                </h1>
                <xsl:for-each select="rss/channel/item">
                    <div class="item">
                        <h2><a href="{link}"><xsl:value-of select="title" /></a></h2>
                        <div><xsl:value-of select="description" disable-output-escaping="yes" /></div>
                    </div>
                </xsl:for-each>
            </body>
        </html>
    </xsl:template>
</xsl:stylesheet>
