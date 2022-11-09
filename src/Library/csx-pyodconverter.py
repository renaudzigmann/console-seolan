# CSX PyODConverter from :
#
# PyODConverter (Python OpenDocument Converter) v1.5 - 2013-01-07
#
# This script converts a document from one office format to another by
# connecting to an LibreOffice instance via Python-UNO bridge.
#
# Copyright (C) 2008-2013 Mirko Nasato
# Licensed under the GNU LGPL v2.1 - http://www.gnu.org/licenses/lgpl-2.1.html
# or any later version.
#
# CSX PyODConverter
# improvment of the PyDOConverter script with functions to convert document other than draw or impress to image png
# and in this case page per page conversion
#
DEFAULT_OPENOFFICE_PORT = 8102

import uno
import os
import getpass

from os.path import abspath, isfile, splitext, basename, dirname, isdir
from os import system
from sys import exit
from shutil import copyfile
from com.sun.star.awt import Size
from com.sun.star.beans import PropertyValue
from com.sun.star.view.PaperFormat import USER
from com.sun.star.view.PaperOrientation import PORTRAIT, LANDSCAPE
from com.sun.star.task import ErrorCodeIOException
from com.sun.star.connection import NoConnectException
FAMILY_TEXT = "Text"
FAMILY_WEB = "Web"
FAMILY_SPREADSHEET = "Spreadsheet"
FAMILY_PRESENTATION = "Presentation"
FAMILY_DRAWING = "Drawing"


#---------------------#
# Configuration Start #
#---------------------#

'''
See http://www.openoffice.org/api/docs/common/ref/com/sun/star/view/PaperFormat.html
'''
PAPER_SIZE_MAP = {
    "A5": Size(14800,21000),
    "A4": Size(21000,29700),
    "A3": Size(29700,42000),
    "LETTER": Size(21590,27940),
    "LEGAL": Size(21590,35560),
    "TABLOID": Size(27900,43200)
}

'''
See http://www.openoffice.org/api/docs/common/ref/com/sun/star/view/PaperOrientation.html
'''
PAPER_ORIENTATION_MAP = {
    "PORTRAIT": PORTRAIT,
    "LANDSCAPE": LANDSCAPE
}

'''
See http://wiki.services.openoffice.org/wiki/Framework/Article/Filter
most formats are auto-detected; only those requiring options are defined here
'''
IMPORT_FILTER_MAP = {
    "txt": {
        "FilterName": "Text (encoded)",
        "FilterOptions": "utf8"
    },
    "csv": {
        "FilterName": "Text - txt - csv (StarCalc)",
        "FilterOptions": "44,34,0"
    }
}

'''
The filter options to export PDF files can be viewed on URL below
http://wiki.openoffice.org/wiki/API/Tutorials/PDF_export#General_properties
'''
EXPORT_FILTER_MAP = {
    "pdf": {
        FAMILY_TEXT: {
            "FilterName": "writer_pdf_Export",
            "FilterData": {
                "IsSkipEmptyPages": True
            },
            "Overwrite": True
        },
        FAMILY_WEB: {
            "FilterName": "writer_web_pdf_Export",
            "FilterData": {
                "IsSkipEmptyPages": True
            },
            "Overwrite": True
        },
        FAMILY_SPREADSHEET: {
            "FilterName": "calc_pdf_Export",
            "FilterData": {
                "IsSkipEmptyPages": True
            },
            "Overwrite": True
        },
        FAMILY_PRESENTATION: {
            "FilterName": "impress_pdf_Export",
            "FilterData": {
                "IsSkipEmptyPages": True
            },
            "Overwrite": True
        },
        FAMILY_DRAWING: {
            "FilterName": "draw_pdf_Export",
            "FilterData": {
                "IsSkipEmptyPages": True
            },
            "Overwrite": True
        }
    },
    "html": {
        FAMILY_TEXT: {
            "FilterName": "HTML (StarWriter)",
            "Overwrite": True
        },
        FAMILY_SPREADSHEET: {
            "FilterName": "HTML (StarCalc)",
            "Overwrite": True
        },
        FAMILY_PRESENTATION: {
            "FilterName": "impress_html_Export",
            "Overwrite": True
        }
    },
    "odt": {
        FAMILY_TEXT: {
            "FilterName": "writer8",
            "Overwrite": True
        },
        FAMILY_WEB: {
            "FilterName": "writerweb8_writer",
            "Overwrite": True
        }
    },
    "doc": {
        FAMILY_TEXT: {
            "FilterName": "MS Word 97",
            "Overwrite": True
        }
    },
    "docx": {
        FAMILY_TEXT: {
            "FilterName": "MS Word 2007 XML",
            "Overwrite": True
        }
    },
    "rtf": {
        FAMILY_TEXT: {
            "FilterName": "Rich Text Format",
            "Overwrite": True
        }
    },
    "txt": {
        FAMILY_TEXT: {
            "FilterName": "Text",
            "FilterOptions": "utf8",
            "Overwrite": True
        }
    },
    "ods": {
        FAMILY_SPREADSHEET: {
            "FilterName": "calc8",
            "Overwrite": True
        }
    },
    "xls": {
        FAMILY_SPREADSHEET: {
            "FilterName": "MS Excel 97",
            "Overwrite": True
        }
    },
    "csv": {
        FAMILY_SPREADSHEET: {
            "FilterName": "Text - txt - csv (StarCalc)",
            "FilterOptions": "44,34,0",
            "Overwrite": True
        }
    },
    "odp": {
        FAMILY_PRESENTATION: {
            "FilterName": "impress8",
            "Overwrite": True
        }
    },
    "ppt": {
        FAMILY_PRESENTATION: {
            "FilterName": "MS PowerPoint 97",
            "Overwrite": True
        }
    },
    "pptx": {
        FAMILY_PRESENTATION: {
            "FilterName": "Impress MS PowerPoint 2007 XML",
            "Overwrite": True
        }
    },
    "swf": {
        FAMILY_DRAWING: {
            "FilterName": "draw_flash_Export",
            "Overwrite": True
        },
        FAMILY_PRESENTATION: {
            "FilterName": "impress_flash_Export",
            "Overwrite": True
        }
    },
    "png": {
        FAMILY_PRESENTATION: {
            "FilterName": "impress_png_Export",
            "Overwrite": True
        },
        FAMILY_DRAWING: {
            "FilterName": "draw_png_Export",
            "Overwrite": True
        },
        FAMILY_TEXT:{
				"FilterName":"writer_png_Export",
				"FilterData":{"PixelWidth":800,"PixelHeight":1200},
				"Overwrite":True
		},
        FAMILY_SPREADSHEET:{
            "FilterName":"calc_png_Export",
            #"FilterData":{"PixelWidth":1600,"PixelHeight":2400,"LogicalWidth":1600, "LogicalHeight":2400},
            "Overwrite":True
        }
    },
    "gif": {
        FAMILY_PRESENTATION: {
            "FilterName": "impress_gif_Export",
            "Overwrite": True
        },
        FAMILY_DRAWING: {
            "FilterName": "draw_gif_Export",
            "Overwrite": True
        }
    },
    "jpg": {
        FAMILY_PRESENTATION: {
            "FilterName": "impress_jpg_Export",
            "Overwrite": True
        },
        FAMILY_DRAWING: {
            "FilterName": "draw_jpg_Export",
            "Overwrite": True
        }
    }
}

PAGE_STYLE_OVERRIDE_PROPERTIES = {
    FAMILY_SPREADSHEET: {
        #--- Scale options: uncomment 1 of the 3 ---
        # a) 'Reduce / enlarge printout': 'Scaling factor'
        "PageScale": 100,
        # b) 'Fit print range(s) to width / height': 'Width in pages' and 'Height in pages'
        #"ScaleToPagesX": 1, "ScaleToPagesY": 1000,
        # c) 'Fit print range(s) on number of pages': 'Fit print range(s) on number of pages'
        #"ScaleToPages": 1,
        "PrintGrid": False
    }
}

IMAGES_MEDIA_TYPE = {
    "png": "image/png",
    "jpeg": "image/jpeg",
    "jpg": "image/jpeg",
    "gif": "image/gif",
    "pdf": "application/pdf"
}

#-------------------#
# Configuration End #
#-------------------#

class DocumentConversionException(Exception):

    def _get_message(self):
        return self._message

    def _set_message(self, message):
        self._message = message

    message = property(_get_message, _set_message)


class DocumentConverter:

    def __init__(self, listener=('localhost', DEFAULT_OPENOFFICE_PORT), tmpDir="/tmp/", debug=None):
        self.tmpDir = tmpDir
        self.debug = debug
        address, port = listener
        localContext = uno.getComponentContext()
        resolver = localContext.ServiceManager.createInstanceWithContext("com.sun.star.bridge.UnoUrlResolver", localContext)
        try:
            # com.sun.star.uno.xinterface
            self.context = resolver.resolve("uno:socket,host={0},port={1};urp;StarOffice.ComponentContext".format(address, port))
        except NoConnectException:
            raise DocumentConversionException("failed to connect to LibreOffice on {0}:{1}".format(address, port))
        self.desktop = self.context.ServiceManager.createInstanceWithContext("com.sun.star.frame.Desktop", self.context)

    def __del__(self):
        if hasattr(self, "context"):
            print("release")
            # pas bon,non self.context.release()

    def convert(self, inputFile, outputFile, paperSize="A4", paperOrientation="PORTRAIT"):

        if not paperSize in PAPER_SIZE_MAP:
            raise Exception("The paper size given doesn't exist.")
        else:
            paperSize = PAPER_SIZE_MAP[paperSize]

        if not paperOrientation in PAPER_ORIENTATION_MAP:
            raise Exception("The paper orientation given doesn't exist.")
        else:
            paperOrientation = PAPER_ORIENTATION_MAP[paperOrientation]

        inputUrl = self._toFileUrl(inputFile)
        outputUrl = self._toFileUrl(outputFile)

        loadProperties = { "Hidden": True }

        inputExt = self._getFileExt(inputFile)
        outputExt = self._getFileExt(outputFile);

        if inputExt in IMPORT_FILTER_MAP:
            loadProperties.update(IMPORT_FILTER_MAP[inputExt])

        try:
            document = self.desktop.loadComponentFromURL(inputUrl, "_blank", 0, self._toProperties(loadProperties))
        except Exception as error:
            """
            Just remainder:
            com.sun.star.lang.IllegalArgumentException: Unsupported URL
            is like 404 Not Found
            """
            raise DocumentConversionException(str(error))
        try:
            document.refresh()
        except AttributeError:
            pass

        family = self._detectFamily(document)

        try:
            '''
            If you wish convert a document to an image, so each page needs be converted to a individual image.
            '''
            if outputExt in IMAGES_MEDIA_TYPE:

                have_pages = getattr(document, 'getDrawPages', None)
                if not have_pages:
                    raise DocumentConversionException("document doesn have pages")
                drawPages = document.getDrawPages()
                pagesTotal = drawPages.getCount()
                mediaType = IMAGES_MEDIA_TYPE[outputExt]
                fileBasename = self._getFileBasename(outputUrl)
                graphicExport = self.context.ServiceManager.createInstanceWithContext("com.sun.star.drawing.GraphicExportFilter", self.context)

                for pageIndex in range(pagesTotal):

                    page = drawPages.getByIndex(pageIndex)
                    fileName = "%s-%d.%s" % (self._getFileBasename(outputUrl), pageIndex, outputExt)

                    graphicExport.setSourceDocument( page )

                    props = {
                        "MediaType": mediaType,
                        "URL": fileName
                    }

                    graphicExport.filter( self._toProperties( props ) )
            else:

                self._overridePageStyleProperties(document, family)

                storeProperties = self._getStoreProperties(document, outputExt)

                printConfigs = {
                    'AllSheets': True,
                    'Size': paperSize,
                    'PaperFormat': USER,
                    'PaperOrientation': paperOrientation
                }

                document.setPrinter( self._toProperties( printConfigs ) )

                document.storeToURL(outputUrl, self._toProperties(storeProperties))
        finally:
            document.close(True)

    def exportPageImage(self, filein, fileout, pages):
        """
            surcharge de convert pour gérer le page num
        """
        pagesCount, genFiles = self._exportPageImage(filein, fileout, pages)

        return "{},'{}'".format(pagesCount, "','".join(genFiles))

    def _exportPageImage(self, inputFile, outputFile, pages=None):
        """
        Extract requerid pages (or first page) of inputfile and export it as a picture
        :param inputFile: file name for page extraction
        :param outputFile: base name for resulting pictures
        :param pages: pages numbler to extract
        :return: (document pages total number, files names generated)
        """

        if pages == None:
            pages = (1, 1)

        if isinstance(pages, str):

            pages = [max(1, int(p)) for p in pages.split(',')]

            if len(pages) == 1:
                pages = pages[0]

        if isinstance(pages, int):
            pages = (pages, pages)

        pagefirst, pagelast = pages

        inputUrl = self._toFileUrl(inputFile)
        outputUrl = self._toFileUrl(outputFile)

        loadProperties = {"Hidden": True}

        inputExt = self._getFileExt(inputFile)
        outputExt = self._getFileExt(outputFile);

        # formatage du outputUrl avec le numero de page
        fileBasename = self._getFileBasename(outputFile)

        self.trace(
            "conversion/extraction from '{}' to '{}' page(s) {} to {}".format(inputUrl, outputUrl, pagefirst, pagelast),
            "trace")

        if inputExt in IMPORT_FILTER_MAP:
            loadProperties.update(IMPORT_FILTER_MAP[inputExt])
        try:
            document = self.desktop.loadComponentFromURL(inputUrl, "_blank", 0, self._toProperties(loadProperties))
        except Exception as error:
            """
            Just remainder:
            com.sun.star.lang.IllegalArgumentException: Unsupported URL
            is like 404 Not Found
            """
            raise DocumentConversionException(str(error))
        try:
            document.refresh()
        except AttributeError:
            pass

        family = self._detectFamily(document)

        genFiles = []
        pagesTotal = None

        try:
            '''
                If you wish convert a document to an image, so each page needs be converted to a individual image.
                (rr) semble ok pour les draw et présentation mais getdrawpage n'est pas ok sur du texte
            '''

            # traitement si input type présentation ou drawing en fait
            if outputExt in IMAGES_MEDIA_TYPE and family in (FAMILY_DRAWING, FAMILY_PRESENTATION):

                mediaType = IMAGES_MEDIA_TYPE[outputExt]

                graphicExport = self.context.ServiceManager.createInstanceWithContext(
                    "com.sun.star.drawing.GraphicExportFilter", self.context)

                have_pages = getattr(document, 'getDrawPages', None)
                page = None
                drawPages = 0

                if not have_pages:
                    # raise Exception("document '{}' doesn't have drawpages".format(filein))
                    pass
                else:
                    from com.sun.star.lang import IndexOutOfBoundsException
                    drawPages = document.getDrawPages()
                    pagesTotal = drawPages.getCount()
                    self.trace("pages found {}".format(pagesTotal))

                if not drawPages or not pagesTotal:
                    raise DocumentConversionException("document '{}' doesn't have page".format(filein))

                if pagefirst > pagesTotal:
                    self.trace("first page {} out of range, pages count {}".format(pagefirst, pagesTotal),
                               "trace")
                    exit(1)

                for pagenum in range(pagefirst, min(pagelast, pagesTotal) + 1):
                    page = drawPages.getByIndex(pagenum - 1)  # tester indexoutofbound ?
                    if page:
                        fileName = self.outputFileName(fileBasename, pagenum, outputExt)
                        outputUrl = self._toFileUrl(fileName)

                        graphicExport.setSourceDocument(page)

                        props = {
                            "MediaType": mediaType,
                            "URL": outputUrl
                        }

                        graphicExport.filter(self._toProperties(props))

                        genFiles.append(fileName)

            elif outputExt in IMAGES_MEDIA_TYPE and family in (FAMILY_TEXT, FAMILY_WEB):

                self._overridePageStyleProperties(document, family)

                storeProperties = self._getStoreProperties(document, outputExt)

                self.trace(storeProperties)

                # https://wiki.openoffice.org/wiki/Documentation/DevGuide/Text/TextViewCursor
                docController = document.getCurrentController()
                textviewcursor = docController.getViewCursor()
                if pagefirst > docController.PageCount:
                    self.trace("first page {} out of range, pages count {}".format(pagefirst, docController.PageCount),
                               "trace")
                    exit(1)

                pagesTotal = docController.PageCount

                for pagenum in range(pagefirst, min(pagelast, docController.PageCount) + 1):
                    self.trace("===> {} in {}".format(pagenum, docController.PageCount))

                    fileName = self.outputFileName(fileBasename, pagenum, outputExt)
                    outputUrl = self._toFileUrl(fileName)

                    # if pagenum > last page, stay on last page, no error raised
                    textviewcursor.jumpToPage(pagenum)

                    self.trace("\n".join(repr(docController).split(",")))

                    document.storeToURL(outputUrl, self._toProperties(storeProperties))

                    genFiles.append(fileName)

            elif outputExt in IMAGES_MEDIA_TYPE and family in (FAMILY_SPREADSHEET):

                docController = document.getCurrentController()
                #sheets = document.getSheets()
                #oSheet = sheets.getByIndex(0)
                #docController.select(oSheet)
                #oDispatcher = self.createUnoService("com.sun.star.frame.DispatchHelper")
                #oSpreadsheetFrame = docController.getFrame()
                #oDispatcher.executeDispatch(oSpreadsheetFrame, ".uno:Copy", "", 0, Array())
                #textviewcursor = docController.getViewData()
                #print(textviewcursor)
                #textviewcursor.jumpToPage(1)
                have_pages = getattr(document, 'getDrawPages', None)
                page = None
                if not have_pages:
                    # raise Exception("document '{}' doesn't have drawpages".format(filein))
                    pass
                else:
                    from com.sun.star.lang import IndexOutOfBoundsException
                    drawPages = document.getDrawPages()
                    pagesTotal = drawPages.getCount()
                    self.trace("pages found {}".format(pagesTotal))

                self._overridePageStyleProperties(document, family)
                storeProperties = self._getStoreProperties(document, outputExt)
                # self.trace(storeProperties)

                fileName = self.outputFileName(fileBasename, 1, outputExt)
                #fileName = "{}.{}".format(fileBasename, outputExt)
                outputUrl = self._toFileUrl(fileName)
                document.storeToURL(outputUrl, self._toProperties(storeProperties))
                genFiles.append(fileName)

            else:
                raise DocumentConversionException('format / family not supported')
        finally:
            document.close(True)

        return (pagesTotal, genFiles)

    def _overridePageStyleProperties(self, document, family):
        if family in PAGE_STYLE_OVERRIDE_PROPERTIES:
            styleFamilies = document.getStyleFamilies()
            if styleFamilies.hasByName('PageStyles'):
                properties = PAGE_STYLE_OVERRIDE_PROPERTIES[family]
                pageStyles = styleFamilies.getByName('PageStyles')
                for styleName in pageStyles.getElementNames():
                    pageStyle = pageStyles.getByName(styleName)
                    for name, value in properties.items():
                        pageStyle.setPropertyValue(name, value)

    def _getStoreProperties(self, document, outputExt):
        family = self._detectFamily(document)

        try:
            propertiesByFamily = EXPORT_FILTER_MAP[outputExt]

        except KeyError:
            raise DocumentConversionException("unknown output format: '%s'" % outputExt)
        try:
            return propertiesByFamily[family]

        except KeyError:
            raise DocumentConversionException("unsupported conversion: from '%s' to '%s'" % (family, outputExt))

    def _detectFamily(self, document):
        if document.supportsService("com.sun.star.text.WebDocument"):
            return FAMILY_WEB
        if document.supportsService("com.sun.star.text.GenericTextDocument"):
            # must be TextDocument or GlobalDocument
            return FAMILY_TEXT
        if document.supportsService("com.sun.star.sheet.SpreadsheetDocument"):
            return FAMILY_SPREADSHEET
        if document.supportsService("com.sun.star.presentation.PresentationDocument"):
            return FAMILY_PRESENTATION
        if document.supportsService("com.sun.star.drawing.DrawingDocument"):
            return FAMILY_DRAWING
        raise DocumentConversionException("unknown document family: %s" % document)

    def _getFileExt(self, path):
        ext = splitext(path)[1]
        if ext is not None:
            return ext[1:].lower()

    def _getFileBasename(self, path):
        name = splitext(path)[0]
        if name is not None:
            return name

    def createUnoService(self, serviceName):
        sm = uno.getComponentContext().ServiceManager
        return sm.createInstanceWithContext(serviceName, uno.getComponentContext())

    def _toFileUrl(self, path):
        return uno.systemPathToFileUrl(abspath(path))

    def _toProperties(self, options):
        props = []
        for key in options:
            if isinstance(options[key], dict):
                property = PropertyValue(key, 0, uno.Any("[]com.sun.star.beans.PropertyValue", (self._toProperties(options[key]))), 0)
            else:
                property = PropertyValue(key, 0, options[key], 0)
            props.append(property)
        return tuple(props)

    def _dump(self, obj):
        for attr in dir(obj):
            print("obj.%s = %s\n" % (attr, getattr(obj, attr)))

    def outputFileName(self, fileBasename, pagenum, outputExt):
        """
        mise en forme d'un nom de fichier en sortie avec numero de page
        :param fileBasename:
        :param pagenum:
        :param outputExt:
        :return: nom de fichier formaté incluant le numero de page
        """
        return "{}-{}.{}".format(fileBasename, pagenum, outputExt)

    def trace(self, something, level="debug"):
        if (level == "debug" and self.debug) or level == "trace":
            print(something)

if __name__ == "__main__":
    from sys import argv, exit

    usage = "USAGE: {} <input-file> <output-file> [export <page-num> <tmp-dir> [debug [<port>]]]".format(argv[0])
    if len(argv) not in (3,6,7,8):
        print(usage)
        exit(255)

    try:
        if len(argv) == 3:
            converter = DocumentConverter(listener=('localhost', DEFAULT_OPENOFFICE_PORT))
            converter.convert(argv[1], argv[2])

        elif (len(argv) >= 6) and argv[3] == 'export':
            debug = False
            if len(argv) >= 7 and argv[6] == "debug":
                debug = True
            port = argv[7] if len(argv) >= 8 else DEFAULT_OPENOFFICE_PORT

            converter = DocumentConverter(listener=('localhost', port), tmpDir=argv[5], debug=debug)
            inbase = basename(argv[1])
            outbase = basename(argv[2])
            outdir = dirname(argv[2])
            filein = converter.tmpDir+inbase
            fileout = converter.tmpDir+outbase

            if not isdir(converter.tmpDir):
                os.mkdir(converter.tmpDir)

            print('cp '+argv[1]+' to '+argv[5])
            # copy file to common accessible location : /tmp/
            copyfile(argv[1], filein)

            if not isfile(filein):
                print("Unable to copy {} to {}".format(argv[1], converter.tmpDir))
                exit(1)

            if not isdir(argv[5]):
                print("Unable to copy {} ".format(argv[5]))
                exit(1)

            result = converter.exportPageImage(filein, fileout, argv[4])

            # copy resulting file to original dir and clean /tmp/
            filesout = [p.strip("'") for p in result.split(",")[1:]]

            for file in filesout:
                if not isfile(file):
                    print("Unable to create {}".format(fileout))
                    exit(1)

                print("mv '"+file+"' to '"+outdir+"'")
                system("mv '{}' '{}'".format(file,outdir))
                #system("rm -f '{}'".format(file))

            system("rm -f {}".format(filein))

        else:
            print(argv)
            print("invalid parameters usage : {}".format(usage))

    except (DocumentConversionException, Exception) as ex:
        print("ERROR! {}".format(str(ex)))
        exit(1)
