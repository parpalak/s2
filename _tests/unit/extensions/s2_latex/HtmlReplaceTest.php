<?php
/**
 * @copyright 2025 Roman Parpalak
 * @license   https://opensource.org/license/mit MIT
 * @package   S2
 */

declare(strict_types=1);

namespace unit\extensions\s2_latex;

use Codeception\Test\Unit;
use s2_extensions\s2_latex\Extension;

class HtmlReplaceTest extends Unit
{
    public function testReplaceHtml()
    {
        $html = '
<p>$$\dvisvgm\definecolor{cyan}{RGB}{0, 200, 250}
\shorthandoff{&quot;}
\usetikzlibrary {shapes.geometric}
\usetikzlibrary{animations}
\begin{tikzpicture}
  \def\a{1} \def\b{3}
  \useasboundingbox (-\b-2*\a-0.1,-\b-2*\a-0.1) rectangle (\b+2*\a+0.1,\b+2*\a+0.1);
  \draw[cyan,very thin] (-\b-2*\a,-\b-2*\a) grid (\b+2*\a,\b+2*\a);
  \node[star,star points=57, star point ratio=1.07,minimum size=6.2cm, draw,fill=white]
       at (0,0);
  \draw[purple,fill] (0:\b) circle (1pt) -- (0,0) circle (1pt) node [midway, sloped, above] {$\b$} ;
  \begin{scope}:rotate = {0s=&quot;0&quot;, (5*\b)s=&quot;360&quot;,repeats}
    \begin{scope} :rotate = {0s=&quot;0&quot;, (5*\a)s=&quot;360&quot;, origin={(\b+\a,0)}, repeats}
      \node [star,star points=19, star point ratio=1.2,minimum size=2.2cm, draw,fill=white]
       at (0:\b+\a) {};
      \draw [purple,fill] (0:\b+\a) circle (1pt) -- (0:\b) circle (1pt) node [midway, sloped, above] {$\a$} ;
    \end{scope}
  \end{scope}
  \coordinate (A) at (1,1.5);
  \node [fill=white,inner sep=1pt,anchor=east,xshift=3pt,yshift=-1pt] at (A) {$\text{обороты: }\,\,\,.$};
  \foreach \t in {3,2,...,0} {
    \node :opacity = {
      3.75*(0) s=&quot;0&quot;,
      3.75*(0+\t) s=&quot;0&quot;,
      3.75*(0.001+\t) s=&quot;1&quot;,
      3.75*(0.999+\t) s=&quot;1&quot;,
      3.75*(1+\t)s=&quot;0&quot;,
      3.75*(4) s=&quot;0&quot;,
      repeats
    } [anchor=east,inner sep=1pt] at (A) {$\t$};
  }
  \foreach \t in {0,1,...,9} {
    \node :opacity = {
      0.375*(0) s=&quot;0&quot;,
      0.375*(0+\t) s=&quot;0&quot;,
      0.375*(0.01+\t) s=&quot;1&quot;,
      0.375*(0.99+\t) s=&quot;1&quot;,
      0.375*(1+\t)s=&quot;0&quot;,
      0.375*(10) s=&quot;0&quot;,
      repeats
    } [anchor=west,inner sep=1.5pt] at (A) {$\t$};
  }
\end{tikzpicture}$$</p>';

        $expected = '
<p><img border="0" style="vertical-align: middle;" src="//i.upmath.me/svg/%5Cdvisvgm%5Cdefinecolor%7Bcyan%7D%7BRGB%7D%7B0%2C%20200%2C%20250%7D%0A%5Cshorthandoff%7B%22%7D%0A%5Cusetikzlibrary%20%7Bshapes.geometric%7D%0A%5Cusetikzlibrary%7Banimations%7D%0A%5Cbegin%7Btikzpicture%7D%0A%20%20%5Cdef%5Ca%7B1%7D%20%5Cdef%5Cb%7B3%7D%0A%20%20%5Cuseasboundingbox%20(-%5Cb-2*%5Ca-0.1%2C-%5Cb-2*%5Ca-0.1)%20rectangle%20(%5Cb%2B2*%5Ca%2B0.1%2C%5Cb%2B2*%5Ca%2B0.1)%3B%0A%20%20%5Cdraw%5Bcyan%2Cvery%20thin%5D%20(-%5Cb-2*%5Ca%2C-%5Cb-2*%5Ca)%20grid%20(%5Cb%2B2*%5Ca%2C%5Cb%2B2*%5Ca)%3B%0A%20%20%5Cnode%5Bstar%2Cstar%20points%3D57%2C%20star%20point%20ratio%3D1.07%2Cminimum%20size%3D6.2cm%2C%20draw%2Cfill%3Dwhite%5D%0A%20%20%20%20%20%20%20at%20(0%2C0)%3B%0A%20%20%5Cdraw%5Bpurple%2Cfill%5D%20(0%3A%5Cb)%20circle%20(1pt)%20--%20(0%2C0)%20circle%20(1pt)%20node%20%5Bmidway%2C%20sloped%2C%20above%5D%20%7B%24%5Cb%24%7D%20%3B%0A%20%20%5Cbegin%7Bscope%7D%3Arotate%20%3D%20%7B0s%3D%220%22%2C%20(5*%5Cb)s%3D%22360%22%2Crepeats%7D%0A%20%20%20%20%5Cbegin%7Bscope%7D%20%3Arotate%20%3D%20%7B0s%3D%220%22%2C%20(5*%5Ca)s%3D%22360%22%2C%20origin%3D%7B(%5Cb%2B%5Ca%2C0)%7D%2C%20repeats%7D%0A%20%20%20%20%20%20%5Cnode%20%5Bstar%2Cstar%20points%3D19%2C%20star%20point%20ratio%3D1.2%2Cminimum%20size%3D2.2cm%2C%20draw%2Cfill%3Dwhite%5D%0A%20%20%20%20%20%20%20at%20(0%3A%5Cb%2B%5Ca)%20%7B%7D%3B%0A%20%20%20%20%20%20%5Cdraw%20%5Bpurple%2Cfill%5D%20(0%3A%5Cb%2B%5Ca)%20circle%20(1pt)%20--%20(0%3A%5Cb)%20circle%20(1pt)%20node%20%5Bmidway%2C%20sloped%2C%20above%5D%20%7B%24%5Ca%24%7D%20%3B%0A%20%20%20%20%5Cend%7Bscope%7D%0A%20%20%5Cend%7Bscope%7D%0A%20%20%5Ccoordinate%20(A)%20at%20(1%2C1.5)%3B%0A%20%20%5Cnode%20%5Bfill%3Dwhite%2Cinner%20sep%3D1pt%2Canchor%3Deast%2Cxshift%3D3pt%2Cyshift%3D-1pt%5D%20at%20(A)%20%7B%24%5Ctext%7B%D0%BE%D0%B1%D0%BE%D1%80%D0%BE%D1%82%D1%8B%3A%20%7D%5C%2C%5C%2C%5C%2C.%24%7D%3B%0A%20%20%5Cforeach%20%5Ct%20in%20%7B3%2C2%2C...%2C0%7D%20%7B%0A%20%20%20%20%5Cnode%20%3Aopacity%20%3D%20%7B%0A%20%20%20%20%20%203.75*(0)%20s%3D%220%22%2C%0A%20%20%20%20%20%203.75*(0%2B%5Ct)%20s%3D%220%22%2C%0A%20%20%20%20%20%203.75*(0.001%2B%5Ct)%20s%3D%221%22%2C%0A%20%20%20%20%20%203.75*(0.999%2B%5Ct)%20s%3D%221%22%2C%0A%20%20%20%20%20%203.75*(1%2B%5Ct)s%3D%220%22%2C%0A%20%20%20%20%20%203.75*(4)%20s%3D%220%22%2C%0A%20%20%20%20%20%20repeats%0A%20%20%20%20%7D%20%5Banchor%3Deast%2Cinner%20sep%3D1pt%5D%20at%20(A)%20%7B%24%5Ct%24%7D%3B%0A%20%20%7D%0A%20%20%5Cforeach%20%5Ct%20in%20%7B0%2C1%2C...%2C9%7D%20%7B%0A%20%20%20%20%5Cnode%20%3Aopacity%20%3D%20%7B%0A%20%20%20%20%20%200.375*(0)%20s%3D%220%22%2C%0A%20%20%20%20%20%200.375*(0%2B%5Ct)%20s%3D%220%22%2C%0A%20%20%20%20%20%200.375*(0.01%2B%5Ct)%20s%3D%221%22%2C%0A%20%20%20%20%20%200.375*(0.99%2B%5Ct)%20s%3D%221%22%2C%0A%20%20%20%20%20%200.375*(1%2B%5Ct)s%3D%220%22%2C%0A%20%20%20%20%20%200.375*(10)%20s%3D%220%22%2C%0A%20%20%20%20%20%20repeats%0A%20%20%20%20%7D%20%5Banchor%3Dwest%2Cinner%20sep%3D1.5pt%5D%20at%20(A)%20%7B%24%5Ct%24%7D%3B%0A%20%20%7D%0A%5Cend%7Btikzpicture%7D" alt="\dvisvgm\definecolor{cyan}{RGB}{0, 200, 250}
\shorthandoff{&quot;}
\usetikzlibrary {shapes.geometric}
\usetikzlibrary{animations}
\begin{tikzpicture}
  \def\a{1} \def\b{3}
  \useasboundingbox (-\b-2*\a-0.1,-\b-2*\a-0.1) rectangle (\b+2*\a+0.1,\b+2*\a+0.1);
  \draw[cyan,very thin] (-\b-2*\a,-\b-2*\a) grid (\b+2*\a,\b+2*\a);
  \node[star,star points=57, star point ratio=1.07,minimum size=6.2cm, draw,fill=white]
       at (0,0);
  \draw[purple,fill] (0:\b) circle (1pt) -- (0,0) circle (1pt) node [midway, sloped, above] {$\b$} ;
  \begin{scope}:rotate = {0s=&quot;0&quot;, (5*\b)s=&quot;360&quot;,repeats}
    \begin{scope} :rotate = {0s=&quot;0&quot;, (5*\a)s=&quot;360&quot;, origin={(\b+\a,0)}, repeats}
      \node [star,star points=19, star point ratio=1.2,minimum size=2.2cm, draw,fill=white]
       at (0:\b+\a) {};
      \draw [purple,fill] (0:\b+\a) circle (1pt) -- (0:\b) circle (1pt) node [midway, sloped, above] {$\a$} ;
    \end{scope}
  \end{scope}
  \coordinate (A) at (1,1.5);
  \node [fill=white,inner sep=1pt,anchor=east,xshift=3pt,yshift=-1pt] at (A) {$\text{обороты: }\,\,\,.$};
  \foreach \t in {3,2,...,0} {
    \node :opacity = {
      3.75*(0) s=&quot;0&quot;,
      3.75*(0+\t) s=&quot;0&quot;,
      3.75*(0.001+\t) s=&quot;1&quot;,
      3.75*(0.999+\t) s=&quot;1&quot;,
      3.75*(1+\t)s=&quot;0&quot;,
      3.75*(4) s=&quot;0&quot;,
      repeats
    } [anchor=east,inner sep=1pt] at (A) {$\t$};
  }
  \foreach \t in {0,1,...,9} {
    \node :opacity = {
      0.375*(0) s=&quot;0&quot;,
      0.375*(0+\t) s=&quot;0&quot;,
      0.375*(0.01+\t) s=&quot;1&quot;,
      0.375*(0.99+\t) s=&quot;1&quot;,
      0.375*(1+\t)s=&quot;0&quot;,
      0.375*(10) s=&quot;0&quot;,
      repeats
    } [anchor=west,inner sep=1.5pt] at (A) {$\t$};
  }
\end{tikzpicture}" /></p>';

        $this->assertEquals($expected, Extension::convertLatexInHtml($html));
    }
}
