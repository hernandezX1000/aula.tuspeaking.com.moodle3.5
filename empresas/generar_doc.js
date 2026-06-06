const { Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, 
        HeadingLevel, AlignmentType, BorderStyle, WidthType, ShadingType,
        LevelFormat, PageBreak } = require('docx');
const fs = require('fs');

const border = { style: BorderStyle.SINGLE, size: 1, color: "CCCCCC" };
const borders = { top: border, bottom: border, left: border, right: border };
const cellMargins = { top: 80, bottom: 80, left: 120, right: 120 };

function createRow(cells, isHeader = false) {
    return new TableRow({
        children: cells.map((text, i) => new TableCell({
            borders,
            width: { size: cells.length === 2 ? 4680 : 3120, type: WidthType.DXA },
            shading: isHeader ? { fill: "008ba3", type: ShadingType.CLEAR } : undefined,
            margins: cellMargins,
            children: [new Paragraph({ 
                children: [new TextRun({ 
                    text: text, 
                    bold: isHeader,
                    color: isHeader ? "FFFFFF" : "000000",
                    font: "Arial",
                    size: 20
                })] 
            })]
        }))
    });
}

const doc = new Document({
    styles: {
        default: { document: { run: { font: "Arial", size: 22 } } },
        paragraphStyles: [
            { id: "Heading1", name: "Heading 1", basedOn: "Normal", next: "Normal", quickFormat: true,
              run: { size: 36, bold: true, font: "Arial", color: "008ba3" },
              paragraph: { spacing: { before: 400, after: 200 } } },
            { id: "Heading2", name: "Heading 2", basedOn: "Normal", next: "Normal", quickFormat: true,
              run: { size: 28, bold: true, font: "Arial", color: "006d80" },
              paragraph: { spacing: { before: 300, after: 150 } } },
        ]
    },
    numbering: {
        config: [
            { reference: "bullets",
              levels: [{ level: 0, format: LevelFormat.BULLET, text: "•", alignment: AlignmentType.LEFT,
                style: { paragraph: { indent: { left: 720, hanging: 360 } } } }] },
        ]
    },
    sections: [{
        properties: {
            page: {
                size: { width: 12240, height: 15840 },
                margin: { top: 1440, right: 1440, bottom: 1440, left: 1440 }
            }
        },
        children: [
            new Paragraph({
                heading: HeadingLevel.TITLE,
                alignment: AlignmentType.CENTER,
                children: [new TextRun({ text: "DOCUMENTACIÓN TÉCNICA", bold: true, size: 48, color: "008ba3", font: "Arial" })]
            }),
            new Paragraph({
                alignment: AlignmentType.CENTER,
                spacing: { after: 400 },
                children: [new TextRun({ text: "Panel de Gestión de Empresas", size: 32, font: "Arial" })]
            }),
            new Paragraph({
                alignment: AlignmentType.CENTER,
                children: [new TextRun({ text: "tuSpeaking - 6 de Enero de 2026", size: 24, color: "666666", font: "Arial" })]
            }),
            new Paragraph({ children: [new PageBreak()] }),
            
            new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("1. Resumen Ejecutivo")] }),
            new Paragraph({
                spacing: { after: 200 },
                children: [new TextRun({ text: "Sistema completo de gestión de empresas, ediciones y cursos para la plataforma tuSpeaking. Permite crear y administrar la estructura organizativa de los clientes corporativos, incluyendo la creación automatizada de categorías y cursos en Moodle.", font: "Arial" })]
            }),
            new Table({
                width: { size: 100, type: WidthType.PERCENTAGE },
                rows: [
                    createRow(["Componente", "Detalle"], true),
                    createRow(["URL Principal", "https://aula.tuspeaking.com/app/moodle/empresas/admin.php"]),
                    createRow(["Empresas Activas", "10"]),
                    createRow(["Ediciones Totales", "17"]),
                    createRow(["Cursos Configurados", "3,019"]),
                ]
            }),
            
            new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("2. Arquitectura del Sistema")] }),
            new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun("2.1 Jerarquía de Datos")] }),
            new Table({
                width: { size: 100, type: WidthType.PERCENTAGE },
                rows: [
                    createRow(["Nivel", "Tabla", "Descripción"], true),
                    createRow(["1. Empresa", "own_empresas", "Datos del cliente corporativo"]),
                    createRow(["2. Edición", "own_empresa_ediciones", "Períodos formativos con fechas"]),
                    createRow(["3. Categoría", "mdl_course_categories", "Contenedor Moodle de cursos"]),
                    createRow(["4. Curso", "mdl_course", "Cursos individuales"]),
                ]
            }),
            
            new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("3. Base de Datos")] }),
            new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun("3.1 Tabla own_empresas")] }),
            new Table({
                width: { size: 100, type: WidthType.PERCENTAGE },
                rows: [
                    createRow(["Campo", "Tipo", "Descripción"], true),
                    createRow(["id", "BIGINT PK", "Identificador único"]),
                    createRow(["nombre", "VARCHAR(200)", "Nombre de la empresa"]),
                    createRow(["dominio", "VARCHAR(100)", "Dominio email"]),
                    createRow(["activo", "TINYINT(1)", "1=Activa, 0=Inactiva"]),
                ]
            }),
            
            new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun("3.2 Tabla own_empresa_ediciones")] }),
            new Table({
                width: { size: 100, type: WidthType.PERCENTAGE },
                rows: [
                    createRow(["Campo", "Tipo", "Descripción"], true),
                    createRow(["empresa_id", "BIGINT FK", "Referencia a own_empresas"]),
                    createRow(["categoria_id", "BIGINT", "ID categoría Moodle"]),
                    createRow(["fecha_inicio", "DATE", "Inicio del período"]),
                    createRow(["fecha_fin", "DATE", "Fin del período"]),
                ]
            }),
            
            new Paragraph({ children: [new PageBreak()] }),
            
            new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("4. Funcionalidades")] }),
            new Paragraph({ numbering: { reference: "bullets", level: 0 }, children: [new TextRun("Dashboard con KPIs")] }),
            new Paragraph({ numbering: { reference: "bullets", level: 0 }, children: [new TextRun("CRUD completo de empresas")] }),
            new Paragraph({ numbering: { reference: "bullets", level: 0 }, children: [new TextRun("Gestión de ediciones por empresa")] }),
            new Paragraph({ numbering: { reference: "bullets", level: 0 }, children: [new TextRun("Crear categorías Moodle desde el panel")] }),
            new Paragraph({ numbering: { reference: "bullets", level: 0 }, children: [new TextRun("Crear cursos con formato mosaicos y color turquesa")] }),
            new Paragraph({ numbering: { reference: "bullets", level: 0 }, children: [new TextRun("4 mosaicos por defecto en cada curso")] }),
            new Paragraph({ numbering: { reference: "bullets", level: 0 }, children: [new TextRun("Fechas heredadas automáticamente de la edición")] }),
            
            new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("5. Nomenclaturas")] }),
            new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun("5.1 Categorías")] }),
            new Paragraph({ children: [new TextRun("Formato: YYYY - Nombre Empresa (Edición opcional)")] }),
            new Paragraph({ children: [new TextRun("Ejemplo: 2026 - E2Y Commerce (1)")] }),
            
            new Paragraph({ heading: HeadingLevel.HEADING_2, children: [new TextRun("5.2 Cursos")] }),
            new Paragraph({ children: [new TextRun("Formato: [Idioma] [Nivel] - #[ID Moodle]")] }),
            new Paragraph({ children: [new TextRun("Ejemplo: Inglés B1 - #3112")] }),
            
            new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("6. URLs del Sistema")] }),
            new Table({
                width: { size: 100, type: WidthType.PERCENTAGE },
                rows: [
                    createRow(["Función", "URL"], true),
                    createRow(["Panel Empresas", "https://aula.tuspeaking.com/app/moodle/empresas/admin.php"]),
                    createRow(["Panel Feedback", "https://aula.tuspeaking.com/app/moodle/feedback/admin.php"]),
                    createRow(["Course-Acuity", "https://aula.tuspeaking.com/app/moodle/courseacuity.php"]),
                ]
            }),
            
            new Paragraph({ heading: HeadingLevel.HEADING_1, children: [new TextRun("7. Empresas Configuradas")] }),
            new Table({
                width: { size: 100, type: WidthType.PERCENTAGE },
                rows: [
                    createRow(["Empresa", "Dominio", "Ediciones"], true),
                    createRow(["Babel Group", "babelgroup.com", "2"]),
                    createRow(["BeMobile", "bemobile.es", "2"]),
                    createRow(["CESCE", "cesce.es", "1"]),
                    createRow(["E2Y Commerce", "e2ycommerce.com", "4"]),
                    createRow(["Ingeteam", "ingeteam.com", "1"]),
                    createRow(["Lin3s", "lin3s.com", "1"]),
                    createRow(["Lookiero", "lookiero.com", "2"]),
                    createRow(["Rubi", "rubi.com", "1"]),
                    createRow(["SG Tech", "sgtech.tech", "2"]),
                    createRow(["Sodena", "sodena.com", "1"]),
                ]
            }),
            
            new Paragraph({
                spacing: { before: 400 },
                alignment: AlignmentType.CENTER,
                children: [new TextRun({ text: "Documento generado el 6 de enero de 2026 - tuSpeaking", size: 18, color: "666666", italics: true })]
            }),
        ]
    }]
});

Packer.toBuffer(doc).then(buffer => {
    fs.writeFileSync('/home/aulatuspeaking/www/app/moodle/empresas/Documentacion_Panel_Empresas.docx', buffer);
    console.log('OK');
});
