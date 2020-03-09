pdfReport
==============

Use question text to create a pdf report : send it by email, save in survey.

**This plugin is currently not compatible with LimeSurvey 4.X version and up**

## Installation

### Via GIT
- Go to your LimeSurvey Directory
- Change into subdirectory 'plugins'
- Clone in plugins/pdfReport directory `git clone https://gitlab.com/SondagesPro/ExportAndStats/pdfReport.git pdfReport`

### Via ZIP dowload
- Download <https://extensions.sondages.pro/IMG/auto/pdfReport.zip>
- Extract : `unzip pdfReport.zip`
- Move the directory to  plugins/ directory inside LimeSurvey

## Documentation
- Create a upload question type
- Activate pdfReport : _Use this question as pdf report._ to _Yes_
- **The pdf generated use the text of this question** . You can use expression manager, and class and style to make a beautifull report.
- Remind the default system of this question are totally deactivated
- Pdf report are saved as files uploaded in survey
- Pdf is done and saved only when survey is activated, and when user submit the survey
- See other setting

### Style and css usage

You can use inline style in the content of the question text. For example, you can use `<strong style='color:red;font-size:18pt'>A big and red sentence</strong>`.  Remind PDF is not web, usage of position:abolute or float didn't work exactly as excpected.

#### With tcpdf 

By default, the plugin use [tcpdf](https://tcpdf.org/) and [WriteHTML function](https://tcpdf.org/docs/srcdoc/TCPDF/source-class-TCPDF/#17080). The plugin include a basic css file by default. You can replace the css included in the template used by the survey with a `pdfreport.css` in the files directory of the template.

See more example on tcdpf website : [inline style](https://tcpdf.org/examples/example_006/) or usage of a [css file](https://tcpdf.org/examples/example_061/).

#### With limeMpdf plugin

When using [limeMpdf plugin](https://gitlab.com/SondagesPro/coreAndTools/limeMpdf), there are already some CSS class inspired by [Bootstrap](https://getbootstrap.com/docs/3.3/css/). See [limeMdpf demo file](https://gitlab.com/SondagesPro/coreAndTools/limeMpdf/-/blob/master/assets/Demo%20of%20limeMpdf.pdf) for a lot of available class.

You can update more content with limeMpdf using files in your adapted theme templete files.

### New page

Tcpdf can use `<br pagebreak="true" />` or `<page> content </page>` for page broke, you can use it in the content of the question text. HTML is filtered leaving this part.

LimeMpdf use `<pagebreak>` or `<pagebreak />` directly, plugin is adpated to allow `<br pagebreak="true" />` and `</page>` for pagebreak.

### Image inclusion

You can include image with `<img src="/upload/files/picture.png" />` or with [Data URI](https://en.wikipedia.org/wiki/Data_URI_scheme). All image are validated before included in the pdf and replaced by a white 1px size picture if not available. It's better to use local image (or DATA uri) for speedest generation of the pdf.


## Home page & Copyright
- HomePage <http://extensions.sondages.pro/>
- Copyright © 2015-2020 Denis Chenu <https://sondages.pro>
- Copyright © 2017 Réseau en scène Languedoc-Roussillon <https://www.reseauenscene.fr/>
- Copyright © 2015 Ingeus <http://www.ingeus.fr/>
- [![Donate](https://liberapay.com/assets/widgets/donate.svg)](https://liberapay.com/SondagesPro/) : [Donate on Liberapay](https://liberapay.com/SondagesPro/)

Distributed under [GNU AFFERO GENERAL PUBLIC LICENSE Version 3](http://www.gnu.org/licenses/agpl.txt) licence
