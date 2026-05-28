param(
    [string]$OutputPath = "D:\plantAssistant\Пояснительная записка\Защитная презентация.pptx"
)

$ErrorActionPreference = "Stop"

function Escape-Xml {
    param([string]$Text)
    if ($null -eq $Text) { return "" }
    return [System.Security.SecurityElement]::Escape($Text)
}

function New-ParagraphXml {
    param(
        [string]$Text,
        [int]$Size = 1800,
        [string]$Color = "23352B",
        [bool]$Bold = $false
    )

    $escaped = Escape-Xml $Text
    $boldAttr = if ($Bold) { ' b="1"' } else { "" }

    return "<a:p><a:r><a:rPr lang=`"ru-RU`" sz=`"$Size`" dirty=`"0`" smtClean=`"0`"$boldAttr><a:solidFill><a:srgbClr val=`"$Color`"/></a:solidFill></a:rPr><a:t>$escaped</a:t></a:r><a:endParaRPr lang=`"ru-RU`" sz=`"$Size`" dirty=`"0`"/></a:p>"
}

function New-TextBodyXml {
    param(
        [string[]]$Lines,
        [int]$Size = 1800,
        [string]$Color = "23352B",
        [bool]$Bold = $false
    )

    $builder = New-Object System.Text.StringBuilder
    foreach ($line in $Lines) {
        [void]$builder.Append((New-ParagraphXml -Text $line -Size $Size -Color $Color -Bold $Bold))
    }
    return $builder.ToString()
}

function New-RectShapeXml {
    param(
        [int]$Id,
        [string]$Name,
        [int]$X,
        [int]$Y,
        [int]$Cx,
        [int]$Cy,
        [string]$Fill = "2F7D4E",
        [string]$Line = "2F7D4E"
    )

    return @"
<p:sp>
  <p:nvSpPr>
    <p:cNvPr id="$Id" name="$Name"/>
    <p:cNvSpPr/>
    <p:nvPr/>
  </p:nvSpPr>
  <p:spPr>
    <a:xfrm>
      <a:off x="$X" y="$Y"/>
      <a:ext cx="$Cx" cy="$Cy"/>
    </a:xfrm>
    <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
    <a:solidFill><a:srgbClr val="$Fill"/></a:solidFill>
    <a:ln><a:solidFill><a:srgbClr val="$Line"/></a:solidFill></a:ln>
  </p:spPr>
  <p:txBody>
    <a:bodyPr/>
    <a:lstStyle/>
    <a:p/>
  </p:txBody>
</p:sp>
"@
}

function New-TextShapeXml {
    param(
        [int]$Id,
        [string]$Name,
        [int]$X,
        [int]$Y,
        [int]$Cx,
        [int]$Cy,
        [string[]]$Lines,
        [int]$Size = 1800,
        [string]$Color = "23352B",
        [bool]$Bold = $false
    )

    $bodyXml = New-TextBodyXml -Lines $Lines -Size $Size -Color $Color -Bold $Bold

    return @"
<p:sp>
  <p:nvSpPr>
    <p:cNvPr id="$Id" name="$Name"/>
    <p:cNvSpPr txBox="1"/>
    <p:nvPr/>
  </p:nvSpPr>
  <p:spPr>
    <a:xfrm>
      <a:off x="$X" y="$Y"/>
      <a:ext cx="$Cx" cy="$Cy"/>
    </a:xfrm>
    <a:prstGeom prst="rect"><a:avLst/></a:prstGeom>
    <a:noFill/>
    <a:ln><a:noFill/></a:ln>
  </p:spPr>
  <p:txBody>
    <a:bodyPr wrap="square" rtlCol="0" anchor="t"/>
    <a:lstStyle/>
    $bodyXml
  </p:txBody>
</p:sp>
"@
}

function New-SlideXml {
    param(
        [string]$Title,
        [string[]]$Lines,
        [int]$SlideNumber
    )

    $titleShape = New-TextShapeXml -Id 3 -Name "Title" -X 420000 -Y 200000 -Cx 10200000 -Cy 520000 -Lines @($Title) -Size 2600 -Color "FFFFFF" -Bold $true
    $contentShape = New-TextShapeXml -Id 4 -Name "Content" -X 600000 -Y 1180000 -Cx 10800000 -Cy 4800000 -Lines $Lines -Size 1800 -Color "23352B" -Bold $false
    $slideNumShape = New-TextShapeXml -Id 5 -Name "SlideNumber" -X 11100000 -Y 6200000 -Cx 500000 -Cy 200000 -Lines @("$SlideNumber") -Size 1200 -Color "6C7A70" -Bold $true

    return @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cSld>
    <p:bg>
      <p:bgPr>
        <a:solidFill><a:srgbClr val="F4F7F1"/></a:solidFill>
      </p:bgPr>
    </p:bg>
    <p:spTree>
      <p:nvGrpSpPr>
        <p:cNvPr id="1" name=""/>
        <p:cNvGrpSpPr/>
        <p:nvPr/>
      </p:nvGrpSpPr>
      <p:grpSpPr>
        <a:xfrm>
          <a:off x="0" y="0"/>
          <a:ext cx="0" cy="0"/>
          <a:chOff x="0" y="0"/>
          <a:chExt cx="0" cy="0"/>
        </a:xfrm>
      </p:grpSpPr>
      $(New-RectShapeXml -Id 2 -Name "TopBand" -X 0 -Y 0 -Cx 12192000 -Cy 930000 -Fill "2F7D4E" -Line "2F7D4E")
      $(New-RectShapeXml -Id 6 -Name "FooterLine" -X 600000 -Y 6040000 -Cx 10800000 -Cy 25000 -Fill "C8D7CA" -Line "C8D7CA")
      $titleShape
      $contentShape
      $slideNumShape
    </p:spTree>
  </p:cSld>
  <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>
</p:sld>
"@
}

$slides = @(
    @{
        Title = "Разработка веб-приложения клуба любителей растений Plant Assistant"
        Lines = @(
            "Выпускная квалификационная работа.",
            "Разрабатываемая система предназначена для учета комнатных растений, планирования ухода и хранения истории действий.",
            "На титульном слайде следует указать ФИО автора, учебную группу, руководителя и год защиты.",
            "Проект совмещает прикладной пользовательский функционал и современный технологический стек."
        )
    },
    @{
        Title = "Актуальность темы"
        Lines = @(
            "Уход за комнатными растениями требует регулярности, контроля и систематического хранения данных.",
            "На практике сведения о растениях, датах полива и других процедурах часто распределены между заметками, календарями и личной памятью пользователя.",
            "Такой подход увеличивает риск пропуска обязательных действий и затрудняет анализ истории ухода.",
            "Поэтому требуется единое веб-приложение, объединяющее учет растений, планирование мероприятий и удобную визуализацию информации."
        )
    },
    @{
        Title = "Цель и задачи проекта"
        Lines = @(
            "Целью работы является разработка веб-приложения для хранения, обработки и визуализации информации о комнатных растениях и мероприятиях по уходу.",
            "Для достижения цели были решены задачи анализа предметной области и существующих аналогов.",
            "Также были определены требования к системе, спроектированы архитектура и модель данных, реализованы backend и frontend.",
            "Дополнительно были рассмотрены вопросы безопасности, тестирования, производительности и сопровождения проекта."
        )
    },
    @{
        Title = "Функциональные возможности"
        Lines = @(
            "Система поддерживает регистрацию, авторизацию и управление профилем пользователя.",
            "Пользователь может добавлять, редактировать и удалять растения, группировать их по комнатам и загружать изображения.",
            "Для каждого растения можно задавать интервалы ухода, после чего формируются задачи и календарное представление.",
            "Дополнительно реализованы история выполненных действий, публичная лента, лайки, советы, подписки, жалобы и административная панель."
        )
    },
    @{
        Title = "Архитектура системы"
        Lines = @(
            "Приложение построено по клиент-серверной архитектуре.",
            "Frontend работает в браузере пользователя и обращается к backend через REST API.",
            "Backend отвечает за бизнес-логику, авторизацию, доступ к базе данных и файловому хранилищу.",
            "На этом слайде желательно вставить схему: браузер пользователя -> Vue.js frontend -> Laravel API -> PostgreSQL и storage."
        )
    },
    @{
        Title = "Технологии backend"
        Lines = @(
            "Серверная часть реализована на PHP 8.3 с использованием фреймворка Laravel 13.",
            "Laravel предоставляет маршрутизацию, контроллеры, модели, миграции, middleware, policies, request-классы и API resources.",
            "Для хранения данных используется PostgreSQL, а для объектно-ориентированной работы с данными применяется Eloquent ORM.",
            "Качество backend-кода поддерживается через PHPUnit, OpenAPI-контракт и Laravel Pint."
        )
    },
    @{
        Title = "Почему Laravel и PostgreSQL"
        Lines = @(
            "Laravel выбран как зрелая основа для REST API, поскольку сокращает объем инфраструктурного кода и упрощает сопровождение проекта.",
            "В проекте активно используются middleware, policies, resources и request-классы, что повышает структурированность backend-части.",
            "PostgreSQL хорошо подходит для связанной реляционной модели данных с внешними ключами, ограничениями уникальности и индексами.",
            "Такой стек естественно соответствует задачам проекта, в котором растения, уход, социальные связи и жалобы тесно связаны между собой."
        )
    },
    @{
        Title = "Технологии frontend"
        Lines = @(
            "Клиентская часть реализована на Vue.js 3 как одностраничное приложение.",
            "Для сборки и локальной разработки используется Vite, для маршрутизации - Vue Router.",
            "Управление состоянием построено на Pinia, а формы и валидация реализуются через VeeValidate и Zod.",
            "Для интерфейса и визуализации дополнительно используются Chart.js, vue-chartjs, lucide-vue-next и vue-sonner."
        )
    },
    @{
        Title = "Организация frontend-логики"
        Lines = @(
            "Frontend разделен на страницы, сущности, фичи, виджеты и общий слой shared-логики.",
            "Отдельные Pinia-store отвечают за авторизацию, растения, задачи, социальные функции и административные данные.",
            "Единый API-клиент централизует HTTP-запросы, Bearer token, обработку JSON и ошибок.",
            "На этом слайде желательно разместить схему: UI-компоненты -> Pinia store -> API client -> Laravel API."
        )
    },
    @{
        Title = "Docker и RoadRunner"
        Lines = @(
            "Docker используется как средство воспроизводимого развертывания и уменьшает зависимость проекта от локального окружения.",
            "Контейнеризация позволяет одинаково запускать backend, frontend, PostgreSQL и инфраструктурные сервисы.",
            "RoadRunner является высокопроизводительным application server для PHP и работает через долгоживущие worker-процессы.",
            "За счет однократной загрузки Laravel уменьшаются накладные расходы на инициализацию, что ускоряет API на часто вызываемых маршрутах."
        )
    },
    @{
        Title = "ER-диаграмма, СУБД и ORM"
        Lines = @(
            "Слайд объединяет ER-диаграмму базы данных, PostgreSQL как реляционную СУБД и Eloquent ORM.",
            "Диаграмма должна показывать основные сущности и связи вокруг растений, пользователей, ухода и социальных функций.",
            "PostgreSQL выбран из-за связанной структуры данных, ограничений целостности и поддержки индексов.",
            "Eloquent ORM представляет таблицы и связи в виде моделей Laravel, а миграции управляют схемой базы данных."
        )
    },
    @{
        Title = "Безопасность и контроль качества"
        Lines = @(
            "Авторизация реализована через Laravel Sanctum и Bearer token.",
            "Разграничение доступа выполняется по ролям, middleware и policies.",
            "Валидация данных присутствует на backend и frontend, что уменьшает риск сохранения некорректной информации.",
            "Качество API контролируется через OpenAPI-контракт и PHPUnit, а стиль кода - через Laravel Pint, ESLint и Prettier."
        )
    },
    @{
        Title = "Результаты работы"
        Lines = @(
            "В результате создано полноценное веб-приложение для учета растений, планирования ухода и хранения истории действий.",
            "Разработаны REST API и SPA-интерфейс, поддерживающие личные, публичные и административные сценарии.",
            "Система уже включает ленту, карточки растений, задачи, календарь, профиль, советы, лайки, подписки и жалобы.",
            "На этом слайде желательно показать ключевые скриншоты интерфейса: лента, форма растения, задачи и админ-панель."
        )
    },
    @{
        Title = "Выводы и перспективы"
        Lines = @(
            "Цель работы достигнута, поставленные задачи выполнены.",
            "Выбранный стек технологий соответствует функциональным и эксплуатационным требованиям проекта.",
            "Ключевые технологии проекта: Laravel, Vue.js, PostgreSQL, Docker, RoadRunner, Pinia и Vite.",
            "Перспективами развития являются уведомления, расширенная аналитика, отчеты, экспорт данных и дальнейшая production-оптимизация."
        )
    }
)

$themeXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Plant Assistant Theme">
  <a:themeElements>
    <a:clrScheme name="Plant Assistant">
      <a:dk1><a:srgbClr val="1F2D24"/></a:dk1>
      <a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>
      <a:dk2><a:srgbClr val="23352B"/></a:dk2>
      <a:lt2><a:srgbClr val="F4F7F1"/></a:lt2>
      <a:accent1><a:srgbClr val="2F7D4E"/></a:accent1>
      <a:accent2><a:srgbClr val="6E944A"/></a:accent2>
      <a:accent3><a:srgbClr val="B97735"/></a:accent3>
      <a:accent4><a:srgbClr val="4F8DA5"/></a:accent4>
      <a:accent5><a:srgbClr val="8F5144"/></a:accent5>
      <a:accent6><a:srgbClr val="879B73"/></a:accent6>
      <a:hlink><a:srgbClr val="2B5EA3"/></a:hlink>
      <a:folHlink><a:srgbClr val="68417E"/></a:folHlink>
    </a:clrScheme>
    <a:fontScheme name="Plant Assistant Fonts">
      <a:majorFont>
        <a:latin typeface="Aptos Display"/>
        <a:ea typeface=""/>
        <a:cs typeface=""/>
      </a:majorFont>
      <a:minorFont>
        <a:latin typeface="Aptos"/>
        <a:ea typeface=""/>
        <a:cs typeface=""/>
      </a:minorFont>
    </a:fontScheme>
    <a:fmtScheme name="Plant Assistant Format">
      <a:fillStyleLst>
        <a:solidFill><a:schemeClr val="phClr"/></a:solidFill>
      </a:fillStyleLst>
      <a:lnStyleLst>
        <a:ln w="9525"><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:ln>
      </a:lnStyleLst>
      <a:effectStyleLst><a:effectStyle><a:effectLst/></a:effectStyle></a:effectStyleLst>
      <a:bgFillStyleLst><a:solidFill><a:schemeClr val="phClr"/></a:solidFill></a:bgFillStyleLst>
    </a:fmtScheme>
  </a:themeElements>
  <a:objectDefaults/>
  <a:extraClrSchemeLst/>
</a:theme>
"@

$presentationXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentation xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" saveSubsetFonts="1" autoCompressPictures="0">
  <p:sldMasterIdLst>
    <p:sldMasterId id="2147483648" r:id="rId1"/>
  </p:sldMasterIdLst>
  <p:sldIdLst>
$(for ($i = 0; $i -lt $slides.Count; $i++) { "    <p:sldId id=`"$([int](256 + $i))`" r:id=`"rId$([int](5 + $i))`"/>" })
  </p:sldIdLst>
  <p:sldSz cx="12192000" cy="6858000"/>
  <p:notesSz cx="6858000" cy="9144000"/>
  <p:defaultTextStyle/>
</p:presentation>
"@

$presentationRelsXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="slideMasters/slideMaster1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/presProps" Target="presProps.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/viewProps" Target="viewProps.xml"/>
  <Relationship Id="rId4" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/tableStyles" Target="tableStyles.xml"/>
$(for ($i = 1; $i -le $slides.Count; $i++) { "  <Relationship Id=`"rId$([int](4 + $i))`" Type=`"http://schemas.openxmlformats.org/officeDocument/2006/relationships/slide`" Target=`"slides/slide$i.xml`"/>" })
</Relationships>
"@

$contentTypesXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/ppt/presentation.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml"/>
  <Override PartName="/ppt/presProps.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.presProps+xml"/>
  <Override PartName="/ppt/viewProps.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.viewProps+xml"/>
  <Override PartName="/ppt/tableStyles.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.tableStyles+xml"/>
  <Override PartName="/ppt/slideMasters/slideMaster1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideMaster+xml"/>
  <Override PartName="/ppt/slideLayouts/slideLayout1.xml" ContentType="application/vnd.openxmlformats-officedocument.presentationml.slideLayout+xml"/>
  <Override PartName="/ppt/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
$(for ($i = 1; $i -le $slides.Count; $i++) { "  <Override PartName=`"/ppt/slides/slide$i.xml`" ContentType=`"application/vnd.openxmlformats-officedocument.presentationml.slide+xml`"/>" })
</Types>
"@

$slideMasterXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sldMaster xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:cSld name="Plant Assistant Master">
    <p:bg>
      <p:bgRef idx="1001"><a:schemeClr val="bg1"/></p:bgRef>
    </p:bg>
    <p:spTree>
      <p:nvGrpSpPr>
        <p:cNvPr id="1" name=""/>
        <p:cNvGrpSpPr/>
        <p:nvPr/>
      </p:nvGrpSpPr>
      <p:grpSpPr>
        <a:xfrm>
          <a:off x="0" y="0"/>
          <a:ext cx="0" cy="0"/>
          <a:chOff x="0" y="0"/>
          <a:chExt cx="0" cy="0"/>
        </a:xfrm>
      </p:grpSpPr>
    </p:spTree>
  </p:cSld>
  <p:clrMap bg1="lt1" tx1="dk1" bg2="lt2" tx2="dk2" accent1="accent1" accent2="accent2" accent3="accent3" accent4="accent4" accent5="accent5" accent6="accent6" hlink="hlink" folHlink="folHlink"/>
  <p:sldLayoutIdLst>
    <p:sldLayoutId id="1" r:id="rId1"/>
  </p:sldLayoutIdLst>
  <p:txStyles>
    <p:titleStyle/>
    <p:bodyStyle/>
    <p:otherStyle/>
  </p:txStyles>
</p:sldMaster>
"@

$slideMasterRelsXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="../theme/theme1.xml"/>
</Relationships>
"@

$slideLayoutXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:sldLayout xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" type="blank" preserve="1">
  <p:cSld name="Blank">
    <p:spTree>
      <p:nvGrpSpPr>
        <p:cNvPr id="1" name=""/>
        <p:cNvGrpSpPr/>
        <p:nvPr/>
      </p:nvGrpSpPr>
      <p:grpSpPr>
        <a:xfrm>
          <a:off x="0" y="0"/>
          <a:ext cx="0" cy="0"/>
          <a:chOff x="0" y="0"/>
          <a:chExt cx="0" cy="0"/>
        </a:xfrm>
      </p:grpSpPr>
    </p:spTree>
  </p:cSld>
  <p:clrMapOvr><a:masterClrMapping/></p:clrMapOvr>
</p:sldLayout>
"@

$slideLayoutRelsXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideMaster" Target="../slideMasters/slideMaster1.xml"/>
</Relationships>
"@

$relsRootXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="ppt/presentation.xml"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
</Relationships>
"@

$appXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Application>Microsoft Office PowerPoint</Application>
  <PresentationFormat>On-screen Show (16:9)</PresentationFormat>
  <Slides>$($slides.Count)</Slides>
  <Notes>0</Notes>
  <HiddenSlides>0</HiddenSlides>
  <MMClips>0</MMClips>
  <ScaleCrop>false</ScaleCrop>
  <HeadingPairs>
    <vt:vector size="2" baseType="variant">
      <vt:variant><vt:lpstr>Theme</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>1</vt:i4></vt:variant>
    </vt:vector>
  </HeadingPairs>
  <TitlesOfParts>
    <vt:vector size="1" baseType="lpstr">
      <vt:lpstr>Plant Assistant Theme</vt:lpstr>
    </vt:vector>
  </TitlesOfParts>
  <AppVersion>16.0000</AppVersion>
</Properties>
"@

$now = [DateTime]::UtcNow.ToString("s") + "Z"
$coreXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dc:title>Защитная презентация Plant Assistant</dc:title>
  <dc:creator>Codex</dc:creator>
  <cp:lastModifiedBy>Codex</cp:lastModifiedBy>
  <dcterms:created xsi:type="dcterms:W3CDTF">$now</dcterms:created>
  <dcterms:modified xsi:type="dcterms:W3CDTF">$now</dcterms:modified>
</cp:coreProperties>
"@

$presPropsXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:presentationPr xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">
  <p:showPr useTimings="0"/>
</p:presentationPr>
"@

$viewPropsXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<p:viewPr xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main" lastView="sldView">
  <p:normalViewPr>
    <p:restoredLeft sz="15620"/>
    <p:restoredTop sz="94660"/>
  </p:normalViewPr>
  <p:slideViewPr><p:cSldViewPr snapToGrid="1" snapToObjects="1"/></p:slideViewPr>
  <p:notesTextViewPr><p:cViewPr varScale="1"><p:scale sx="100" sy="100"/><p:origin x="0" y="0"/></p:cViewPr></p:notesTextViewPr>
  <p:gridSpacing cx="78028800" cy="78028800"/>
</p:viewPr>
"@

$tableStylesXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<a:tblStyleLst xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" def="{5C22544A-7EE6-4342-B048-85BDC9FD1C3A}"/>
"@

function Add-ZipEntry {
    param(
        [System.IO.Compression.ZipArchive]$Zip,
        [string]$EntryName,
        [string]$Content
    )

    $entry = $Zip.CreateEntry($EntryName, [System.IO.Compression.CompressionLevel]::Optimal)
    $stream = $entry.Open()
    $writer = New-Object System.IO.StreamWriter($stream, (New-Object System.Text.UTF8Encoding($false)))
    $writer.Write($Content)
    $writer.Dispose()
    $stream.Dispose()
}

if (Test-Path -LiteralPath $OutputPath) {
    Remove-Item -LiteralPath $OutputPath -Force
}

Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem
$fs = [System.IO.File]::Open($OutputPath, [System.IO.FileMode]::Create)
$zip = New-Object System.IO.Compression.ZipArchive($fs, [System.IO.Compression.ZipArchiveMode]::Create, $false)

try {
    Add-ZipEntry -Zip $zip -EntryName "[Content_Types].xml" -Content $contentTypesXml
    Add-ZipEntry -Zip $zip -EntryName "_rels/.rels" -Content $relsRootXml
    Add-ZipEntry -Zip $zip -EntryName "docProps/app.xml" -Content $appXml
    Add-ZipEntry -Zip $zip -EntryName "docProps/core.xml" -Content $coreXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/presentation.xml" -Content $presentationXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/_rels/presentation.xml.rels" -Content $presentationRelsXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/presProps.xml" -Content $presPropsXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/viewProps.xml" -Content $viewPropsXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/tableStyles.xml" -Content $tableStylesXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/slideMasters/slideMaster1.xml" -Content $slideMasterXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/slideMasters/_rels/slideMaster1.xml.rels" -Content $slideMasterRelsXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/slideLayouts/slideLayout1.xml" -Content $slideLayoutXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/slideLayouts/_rels/slideLayout1.xml.rels" -Content $slideLayoutRelsXml
    Add-ZipEntry -Zip $zip -EntryName "ppt/theme/theme1.xml" -Content $themeXml

    for ($i = 0; $i -lt $slides.Count; $i++) {
        $slideNumber = $i + 1
        $slideXml = New-SlideXml -Title $slides[$i].Title -Lines $slides[$i].Lines -SlideNumber $slideNumber
        $slideRelXml = @"
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/slideLayout" Target="../slideLayouts/slideLayout1.xml"/>
</Relationships>
"@
        Add-ZipEntry -Zip $zip -EntryName "ppt/slides/slide$slideNumber.xml" -Content $slideXml
        Add-ZipEntry -Zip $zip -EntryName "ppt/slides/_rels/slide$slideNumber.xml.rels" -Content $slideRelXml
    }
}
finally {
    $zip.Dispose()
    $fs.Dispose()
}

Write-Output "Created: $OutputPath"
