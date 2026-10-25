#!/usr/bin/env python3
"""Build standalone Arabic SVG diagrams for the college portal."""

from __future__ import annotations

import base64
import html
import io
from pathlib import Path

from fontTools.subset import Subsetter
from fontTools.ttLib import TTFont

ROOT = Path("/workspace/docs/diagrams")
FONT_REG = "/usr/share/fonts/truetype/noto/NotoKufiArabic-Regular.ttf"
FONT_BOLD = "/usr/share/fonts/truetype/noto/NotoKufiArabic-Bold.ttf"

NAVY = "#142B4A"
MUTED = "#5C6B7A"
LINE = "#8AA0B8"
PAPER = "#F4F7FB"
WHITE = "#FFFFFF"
ACAD = "#2457A7"
ACAD_SOFT = "#E7F0FA"
FIN = "#8A5A12"
FIN_SOFT = "#F8F1E3"
GATE = "#4A5F81"
GATE_SOFT = "#EEF2F6"
OK = "#166534"
OK_SOFT = "#E7F6EE"
BAD = "#9B2C2C"
BAD_SOFT = "#FDECEC"
WARN = "#8A5A12"
WARN_SOFT = "#FBF3E4"
READ = "#1D4E89"
READ_SOFT = "#E8F0FA"


def esc(value: str) -> str:
    return html.escape(value, quote=True)


class Svg:
    def __init__(self, width: int, height: int, title: str):
        self.w = width
        self.h = height
        self.title = title
        self.parts: list[str] = []
        self.texts: list[str] = [title, "Portal"]

    def text(self, value: str) -> None:
        self.texts.append(value)

    def add(self, chunk: str) -> None:
        self.parts.append(chunk)

    def label(
        self,
        x: float,
        y: float,
        value: str,
        size: int = 14,
        weight: int = 400,
        fill: str = NAVY,
        anchor: str = "start",
    ) -> None:
        self.text(value)
        self.add(
            f'<text x="{x}" y="{y}" text-anchor="{anchor}" direction="rtl" '
            f'unicode-bidi="plaintext" font-size="{size}" font-weight="{weight}" '
            f'fill="{fill}">{esc(value)}</text>'
        )

    def card(
        self,
        x: float,
        y: float,
        w: float,
        h: float,
        kicker: str,
        title: str,
        sub: str,
        stroke: str,
        soft: str,
        ghost: bool = False,
    ) -> None:
        dash = ' stroke-dasharray="7 5"' if ghost else ""
        self.add(
            f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="8" '
            f'fill="{WHITE}" stroke="{stroke}" stroke-width="1.5"{dash}/>'
        )
        self.add(
            f'<rect x="{x}" y="{y}" width="6" height="{h}" rx="3" fill="{stroke}"/>'
        )
        self.label(x + w - 16, y + 26, kicker, 11, 400, MUTED)
        self.label(x + w - 16, y + 50, title, 16, 700, NAVY)
        self.label(x + w - 16, y + 74, sub, 12, 400, MUTED)

    def frame(self, x, y, w, h, title: str, stroke: str, soft: str) -> None:
        self.add(
            f'<rect x="{x}" y="{y}" width="{w}" height="{h}" rx="12" '
            f'fill="{soft}" stroke="{stroke}" stroke-width="1.5"/>'
        )
        self.label(x + w - 20, y + 28, title, 15, 700, stroke)

    def arrow(
        self,
        x1: float,
        y1: float,
        x2: float,
        y2: float,
        color: str,
        label: str = "",
        dashed: bool = False,
        label_dx: float = 0,
        label_dy: float = -10,
    ) -> None:
        dash = ' stroke-dasharray="6 4"' if dashed else ""
        self.add(
            f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{color}" '
            f'stroke-width="1.6" fill="none"{dash}/>'
        )
        # arrow head
        import math

        ang = math.atan2(y2 - y1, x2 - x1)
        size = 8
        left = ang + 2.6
        right = ang - 2.6
        p1 = (x2, y2)
        p2 = (x2 - size * math.cos(left), y2 - size * math.sin(left))
        p3 = (x2 - size * math.cos(right), y2 - size * math.sin(right))
        self.add(
            f'<polygon points="{p1[0]:.1f},{p1[1]:.1f} {p2[0]:.1f},{p2[1]:.1f} '
            f'{p3[0]:.1f},{p3[1]:.1f}" fill="{color}"/>'
        )
        if label:
            mx = (x1 + x2) / 2 + label_dx
            my = (y1 + y2) / 2 + label_dy
            self.pill(mx, my, label, color)

    def poly(self, points: list[tuple[float, float]], color: str, label: str, label_at: tuple[float, float]) -> None:
        d = " ".join(f"{x:.1f},{y:.1f}" for x, y in points)
        self.add(
            f'<polyline points="{d}" fill="none" stroke="{color}" stroke-width="1.6"/>'
        )
        x2, y2 = points[-1]
        x1, y1 = points[-2]
        self.arrow(x1, y1, x2, y2, color)
        # arrow() also draws the last segment again; avoid double line by only drawing head
        # The duplicate segment is acceptable (same pixels). Label:
        self.pill(label_at[0], label_at[1], label, color)

    def pill(self, cx: float, cy: float, label: str, color: str) -> None:
        width = max(64, 12 * len(label) + 20)
        x = cx - width / 2
        y = cy - 11
        self.add(
            f'<rect x="{x:.1f}" y="{y:.1f}" width="{width}" height="22" rx="4" '
            f'fill="{WHITE}" stroke="{color}" stroke-width="1"/>'
        )
        self.label(cx, cy + 5, label, 11, 700, color, anchor="middle")

    def render(self) -> str:
        blob = subset_font("".join(self.texts))
        style = f"""
@font-face {{
  font-family: 'Portal';
  src: url(data:font/ttf;base64,{blob['reg']}) format('truetype');
  font-weight: 400;
}}
@font-face {{
  font-family: 'Portal';
  src: url(data:font/ttf;base64,{blob['bold']}) format('truetype');
  font-weight: 700;
}}
text {{ font-family: 'Portal', 'Noto Kufi Arabic', sans-serif; }}
"""
        body = "\n".join(self.parts)
        return (
            f'<?xml version="1.0" encoding="UTF-8"?>\n'
            f'<svg xmlns="http://www.w3.org/2000/svg" width="{self.w}" height="{self.h}" '
            f'viewBox="0 0 {self.w} {self.h}" xml:lang="ar" direction="rtl">\n'
            f'<title>{esc(self.title)}</title>\n'
            f'<style>{style}</style>\n'
            f'<rect width="100%" height="100%" fill="{PAPER}"/>\n'
            f'{body}\n</svg>\n'
        )


_FONT_CACHE: dict[str, str] = {}


def subset_font(text: str) -> dict[str, str]:
    key = text
    if key in _FONT_CACHE:
        return {"reg": _FONT_CACHE[key + ":r"], "bold": _FONT_CACHE[key + ":b"]}
    extra = "PortalABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_/.:%+×"
    sample = text + extra

    def one(path: str) -> str:
        font = TTFont(path)
        sub = Subsetter()
        sub.populate(text=sample)
        sub.subset(font)
        buf = io.BytesIO()
        font.save(buf)
        return base64.b64encode(buf.getvalue()).decode("ascii")

    reg = one(FONT_REG)
    bold = one(FONT_BOLD)
    _FONT_CACHE[key + ":r"] = reg
    _FONT_CACHE[key + ":b"] = bold
    return {"reg": reg, "bold": bold}


def interfaces() -> Svg:
    svg = Svg(1560, 1490, "ربط الواجهات والاعتماديات")
    svg.label(1532, 46, "ربط الواجهات والاعتماديات", 26, 700, NAVY)
    svg.label(
        1532,
        76,
        "كلية العلوم والتكنولوجيا · جامعة بغداد · العام 2024-2025 · الخط المتصل مسار عمل، والمتقطع قراءة أو إعادة",
        13,
        400,
        MUTED,
    )

    svg.frame(40, 100, 1480, 156, "مدخل منفصل · بلا قائمة جانبية", GATE, GATE_SOFT)
    svg.card(1120, 146, 360, 88, "شاشة 12", "تسجيل كادر جديد", "زائر، قبل أي صلاحية", GATE, GATE_SOFT)
    svg.card(600, 146, 360, 88, "بلا ملف", "تسجيل الدخول", "مذكور من شاشة 12 فقط", GATE, GATE_SOFT, ghost=True)
    svg.arrow(960, 190, 1120, 190, GATE, "بعد التفعيل", dashed=True, label_dy=-18)

    svg.frame(40, 280, 1480, 560, "البوابة الأكاديمية · قائمة واحدة تُصفّى حسب الدور", ACAD, ACAD_SOFT)
    svg.card(1120, 330, 360, 100, "شاشة 1", "لوحة أدمن الكلية", "عميد الكلية الأكاديمي", ACAD, ACAD_SOFT)
    svg.card(600, 330, 360, 100, "شاشة 6", "دليل المستخدمين", "تفعيل الدور ورمز QR", ACAD, ACAD_SOFT)
    svg.card(80, 330, 360, 100, "شاشة 5", "الهوية الأكاديمية", "ختم الشهادات، من الأدمن", ACAD, ACAD_SOFT)
    svg.card(1120, 500, 360, 108, "شاشة 9", "رئيس القسم", "نطاق قسمه فقط", ACAD, ACAD_SOFT)
    svg.card(600, 500, 360, 108, "شاشة 7", "مقرر القسم", "إعداد الجدول ثم الرفع", ACAD, ACAD_SOFT)
    svg.card(80, 500, 360, 108, "بلا ملف", "شؤون الطلبة", "بند قائمة بلا شاشة مستقلة", ACAD, ACAD_SOFT, ghost=True)
    svg.card(1120, 680, 360, 84, "بلا ملف", "مشرف المختبر", "دور بلا مصفوفة في شاشة 6", ACAD, ACAD_SOFT, ghost=True)

    svg.arrow(1300, 234, 780, 330, ACAD, "اعتماد العميد", label_dx=70, label_dy=-6)
    svg.arrow(1300, 500, 1300, 430, ACAD, "للعمادة", label_dx=-70, label_dy=0)
    svg.arrow(960, 554, 1120, 554, ACAD, "رفع للاعتماد", label_dy=-18)
    svg.arrow(1120, 648, 960, 648, ACAD, "إعادة أو رفض", dashed=True, label_dy=-16)
    svg.arrow(600, 554, 440, 554, ACAD, "قراءة جداول", dashed=True, label_dy=-18)
    svg.arrow(1300, 680, 1300, 608, ACAD, "طلب مختبر", label_dx=-70, label_dy=0)

    svg.add(
        f'<rect x="40" y="860" width="1480" height="72" rx="8" fill="{WHITE}" '
        f'stroke="{LINE}" stroke-dasharray="7 5"/>'
    )
    svg.label(
        780,
        892,
        "حد الفصل: لا تنقّل بين القائمتين. الجسر الوحيد قراءة الطالب والقسم والقناة وكتاب الإعفاء",
        14,
        700,
        NAVY,
        "middle",
    )
    svg.label(780, 916, "من السجل الأكاديمي إلى أمين الصندوق وشاشة الأجور", 12, 400, MUTED, "middle")

    svg.frame(40, 952, 1480, 430, "البوابة المالية · قائمة مستقلة وصلاحيات مفصولة", FIN, FIN_SOFT)
    svg.card(1120, 1004, 360, 100, "شاشة 3", "إعدادات مالية", "سنة، إقفال، سقف الخصم", FIN, FIN_SOFT)
    svg.card(600, 1004, 360, 100, "شاشة 2", "الأجور والخصومات", "تعرفة وتقسيط وإعفاء", FIN, FIN_SOFT)
    svg.card(80, 1004, 360, 100, "شاشة 4", "اللوحة المالية", "قراءة الخزنتين بلا كتابة", FIN, FIN_SOFT)
    svg.card(1120, 1172, 360, 108, "شاشة 10", "محاسب المصروفات", "تحرير سند بلا رؤية رصيد", FIN, FIN_SOFT)
    svg.card(600, 1172, 360, 108, "شاشة 11", "أمين الصندوق", "قبض ووصل وإقفال يومي", FIN, FIN_SOFT)
    svg.card(80, 1172, 360, 108, "شاشة 8", "دفتر الأستاذ", "قيد مزدوج مرحّل ومقفول", FIN, FIN_SOFT)

    svg.arrow(1120, 1054, 960, 1054, FIN, "سقف الخصم", label_dy=-20)
    svg.arrow(780, 1104, 780, 1172, FIN, "التعرفة", label_dx=52, label_dy=0)
    svg.arrow(260, 1104, 260, 1172, FIN, "قراءة", dashed=True, label_dx=-48, label_dy=0)
    svg.arrow(600, 1210, 440, 1210, FIN, "ترحيل", label_dy=-18)
    svg.poly(
        [(1300, 1280), (1300, 1348), (260, 1348), (260, 1280)],
        FIN,
        "سند بعد التوقيع المزدوج",
        (780, 1336),
    )

    svg.label(1532, 1436, "الإطار المتصل: قشرة قائمة واحدة. المربع المتقطع: واجهة مذكورة بلا ملف في الأرشيف.", 13, 400, MUTED)
    svg.label(1532, 1462, "المصدر: مجلدات الشاشات من 1 إلى 12. لا يوجد سهم قائمة من البوابة الأكاديمية إلى البوابة المالية.", 13, 400, MUTED)
    return svg


def cell_tone(value: str) -> tuple[str, str]:
    blocked = {"محظور", "لا", "غير مصرح", "لا تعديل", "لا يقبض"}
    if value in blocked or value.startswith("لا "):
        return BAD_SOFT, BAD
    if any(word in value for word in ("كامل", "وصول كامل", "تحكم", "إعداد وإدخال", "إدارة التسجيل", "قبض ووصل")):
        return OK_SOFT, OK
    if any(word in value for word in ("قراءة", "إشراف", "تدقيق", "أثر")):
        return READ_SOFT, READ
    if any(word in value for word in ("محجوب", "فقط", "قسمه", "بلا رصيد")):
        return WARN_SOFT, WARN
    return WHITE, NAVY


def matrix(svg: Svg, x: float, y: float, headers: list[str], rows: list[list[str]], col_w: list[int], row_h: int = 46) -> float:
    height = 44 + row_h * len(rows)
    width = sum(col_w)
    svg.add(f'<rect x="{x}" y="{y}" width="{width}" height="{height}" rx="8" fill="{WHITE}" stroke="{LINE}"/>')
    # header
    cursor = x + width
    for i, (header, w) in enumerate(zip(headers, col_w)):
        cursor -= w
        svg.add(f'<rect x="{cursor}" y="{y}" width="{w}" height="44" fill="{NAVY}"/>')
        svg.label(cursor + w - 12, y + 28, header, 13, 700, WHITE)
    # body
    for r, row in enumerate(rows):
        ry = y + 44 + r * row_h
        if r % 2 == 0:
            svg.add(f'<rect x="{x}" y="{ry}" width="{width}" height="{row_h}" fill="#F8FAFC"/>')
        cursor = x + width
        for c, (value, w) in enumerate(zip(row, col_w)):
            cursor -= w
            if c > 0:
                fill, color = cell_tone(value)
                svg.add(
                    f'<rect x="{cursor + 6}" y="{ry + 8}" width="{w - 12}" height="{row_h - 16}" rx="4" fill="{fill}"/>'
                )
                svg.label(cursor + w - 16, ry + 28, value, 12, 700, color)
            else:
                svg.label(cursor + w - 12, ry + 28, value, 13, 700, NAVY)
            svg.add(
                f'<line x1="{cursor}" y1="{ry}" x2="{cursor}" y2="{ry + row_h}" stroke="#E2E8F0"/>'
            )
        svg.add(f'<line x1="{x}" y1="{ry + row_h}" x2="{x + width}" y2="{ry + row_h}" stroke="#E2E8F0"/>')
    return y + height


def permissions() -> Svg:
    svg = Svg(1680, 1180, "صلاحيات البوابتين")
    svg.label(1652, 48, "صلاحيات البوابتين", 26, 700, NAVY)
    svg.label(1652, 78, "مصفوفة الأكاديمية من شاشة 6. المصفوفة المالية مستنتجة من نصوص شاشات 4 و10 و11.", 14, 400, MUTED)

    svg.label(1652, 124, "البوابة الأكاديمية", 18, 700, ACAD)
    y = matrix(
        svg,
        48,
        144,
        ["الوحدة", "أدمن الكلية", "رئيس القسم", "مقرر القسم", "شؤون الطلبة"],
        [
            ["لوحة المؤشرات", "وصول كامل", "مؤشرات قسمه فقط", "إحصائيات الجداول", "إحصائيات القبول"],
            ["الجداول والقاعات", "اعتماد سيادي نهائي", "مصادقة وتعديل القسم", "إعداد وإدخال", "قراءة وتصدير"],
            ["السجل والإضبارة", "تدقيق ومصادقة وثائق", "سجلات طلبة قسمه", "محظور التعديل", "إدارة التسجيل"],
            ["النتائج", "إصدار الأمر الجامعي", "اعتماد الشيت", "تدقيق اللجان", "أرشفة وإصدار وثائق"],
            ["الهوية والأختام", "تحكم كامل", "غير مصرح", "غير مصرح", "غير مصرح"],
            ["الخزينة والأجور", "محظور", "محظور", "محظور", "محظور"],
        ],
        [280, 320, 320, 320, 320],
        52,
    )
    svg.label(1652, y + 28, "مشرف المختبر وعضو الهيئة التدريسية خارج المصفوفة. المعاونان بطاقات قيادة وليسا دوري دخول.", 13, 400, MUTED)

    svg.label(1652, y + 78, "البوابة المالية", 18, 700, FIN)
    y2 = matrix(
        svg,
        40,
        y + 96,
        ["الوحدة", "مدير الحسابات", "أمين الصندوق", "محاسب المصروفات", "المدقق", "الآمر بالصرف"],
        [
            ["لوحة الخزائن", "كامل", "لا", "محجوب الرصيد", "قراءة", "إشراف"],
            ["القبض والوصولات", "إشراف", "قبض ووصل وإقفال", "لا", "قراءة", "لا يقبض"],
            ["سندات الصرف", "مراجعة وتوقيع", "لا", "تحرير بلا رصيد", "تدقيق", "مصادقة نهائية"],
            ["دفتر الأستاذ", "تسوية وترحيل", "أثر القبض فقط", "لا", "قراءة", "إشراف"],
            ["الأجور والخصومات", "إعداد", "قراءة للتطبيق", "لا", "تدقيق", "خصم فوق 15%"],
            ["الإعدادات المالية", "ضبط", "لا", "لا", "قراءة السجل", "اعتماد الختم"],
            ["الدرجات والجداول", "لا تعديل", "قراءة هوية الطالب", "لا", "لا", "لا تعديل"],
        ],
        [230, 250, 280, 270, 220, 250],
        52,
    )
    svg.label(1652, y2 + 36, "أخضر: صلاحية عمل. أزرق: قراءة أو إشراف. كهرماني: نطاق ضيق. أحمر: ممنوع.", 13, 400, MUTED)
    svg.label(1652, y2 + 60, "شريط القائمة المالية مكرر في القالب. القيد الفعلي هو النص داخل الشاشة، لا وجود البند في الشريط.", 13, 400, MUTED)
    svg.h = int(y2 + 90)
    return svg


ENTITIES = {
    "ref": [
        ("المؤسسة", "الاسم والرمز الوزاري", "5 كتابة · 3 قراءة"),
        ("الأقسام", "الرمز والمراحل والطاقة", "1 و6 و2 و10 قراءة"),
        ("السنة والتقويم", "الفصل وسياسة الإقفال", "3 كتابة · 1 و2 قراءة"),
        ("قنوات الدراسة", "صباحي، مسائي، موازي", "3 كتابة · 2 قراءة"),
        ("القاعات والمختبرات", "السعة والتضارب", "7 ومختبر قراءة"),
        ("الشعار والختم", "نطاق شهادات أو وصولات", "5 كتابة · 3 كتابة"),
        ("الموقّعون", "الأمر الجامعي والنطاق", "5 كتابة · 3 كتابة"),
    ],
    "iam": [
        ("المستخدمون", "الرمز الوظيفي والحالة", "12 إنشاء · 6 إدارة"),
        ("الأدوار", "قشرة أكاديمية أو مالية", "12 و6 قراءة"),
        ("إسناد الدور", "مستخدم + دور + قسم", "6 كتابة"),
        ("الجلسة و QR", "التحقق الثنائي وانتهاء الرمز", "6 كتابة"),
        ("سجل التدقيق", "إلحاق فقط بلا حذف", "1 و4 قراءة · 3 و5 و6 كتابة"),
    ],
    "acad": [
        ("الطلبة", "الرقم الجامعي والاسم", "شؤون كتابة · 11 قراءة"),
        ("القيد الدراسي", "قسم، مرحلة، شعبة، قناة", "شؤون كتابة · 7 و11 قراءة"),
        ("المقررات", "بولونيا، نظري أو عملي", "7 و9 قراءة"),
        ("حصص الجدول", "يوم، قاعة، تدريسي", "7 كتابة · 9 وشؤون قراءة"),
        ("طلبات الجدول", "رفع، اعتماد، إعادة", "7 إنشاء · 9 قرار"),
        ("الغياب والإنذار", "النسبة التراكمية", "9 كتابة"),
        ("كشوف السعي", "لجنة ثم اعتماد الرئيس", "9 كتابة · شؤون قراءة"),
        ("إضبارة الوثائق", "النواقص والمصادقة", "شؤون كتابة · 7 قراءة"),
        ("التعاميم", "مجلس القسم", "9 كتابة"),
        ("طلبات المختبر", "إشغال إضافي", "مختبر إنشاء · 9 قرار"),
        ("الأوامر الجامعية", "إصدار العميد للنتائج", "1 كتابة · شؤون قراءة"),
    ],
    "fin": [
        ("الخزائن", "موازي 3201 ومسائي 3202", "8 كتابة · 4 قراءة"),
        ("تعرفة الأجور", "قسم × قناة × تقسيط", "2 كتابة · 11 قراءة"),
        ("قواعد الإعفاء", "شهداء، أوائل، رعاية", "2 كتابة · 11 قراءة"),
        ("طلبات التخفيض", "معلقة حتى العميد", "2 كتابة · 4 قراءة"),
        ("ذمة الطالب", "مستحق، مسدد، متبقي", "11 كتابة"),
        ("وردية الصندوق", "فتح، إقفال، ترحيل", "11 كتابة · 8 قراءة"),
        ("وصولات القبض", "QR ونسخة الطالب", "11 إنشاء · 4 و8 قراءة"),
        ("سندات الصرف", "مسودة ثم توقيعان", "10 كتابة · 8 قراءة"),
        ("قيود اليومية", "مدين ودائن مقفلان", "8 إدارة · 11 ترحيل قبض"),
    ],
}


def database() -> Svg:
    svg = Svg(1680, 1280, "قاعدة البيانات مربوطة بالواجهات")
    svg.label(1652, 46, "قاعدة البيانات مربوطة بالواجهات", 26, 700, NAVY)
    svg.label(
        1652,
        76,
        "نموذج منطقي من حقول الشاشات. الأرشيف لا يحتوي SQL. الكتابة الأكاديمية لا تصل النطاق المالي.",
        14,
        400,
        MUTED,
    )

    columns = [
        ("fin", "مالي مغلق", FIN, FIN_SOFT, 40),
        ("acad", "أكاديمي", ACAD, ACAD_SOFT, 450),
        ("iam", "الوصول والتدقيق", GATE, GATE_SOFT, 860),
        ("ref", "مرجع مشترك", NAVY, "#E8EEF5", 1270),
    ]
    # RTL visual order: ref is the rightmost column (x=1270)
    top = 110
    card_w = 370
    for key, title, stroke, soft, x in columns:
        items = ENTITIES[key]
        height = 48 + len(items) * 62 + 12
        svg.frame(x, top, card_w, height, title, stroke, soft)
        yy = top + 48
        for name, keys, screens in items:
            svg.add(
                f'<rect x="{x + 12}" y="{yy}" width="{card_w - 24}" height="54" rx="6" '
                f'fill="{WHITE}" stroke="{stroke}" stroke-width="1"/>'
            )
            svg.label(x + card_w - 24, yy + 22, name, 14, 700, NAVY)
            svg.label(x + card_w - 24, yy + 42, f"{keys} · {screens}", 11, 400, MUTED)
            yy += 62

    svg.label(1652, 980, "الجسور المسموحة بين النطاقات", 18, 700, NAVY)
    y = matrix(
        svg,
        40,
        1000,
        ["من", "إلى", "معنى الربط", "الشاشة"],
        [
            ["إسناد الدور", "الأقسام", "رئيس القسم والمقرر يريان قسمهما فقط", "شاشة 6"],
            ["تعرفة الأجور", "القسم والقناة", "تسعير مالي بلا تعديل أكاديمي", "شاشة 2 تكتب و11 تقرأ"],
            ["ذمة الطالب والوصل", "الطلبة", "قراءة عبر الحد والصندوق لا يكتب الدرجة", "شاشة 11"],
            ["سند الصرف", "الأقسام", "اسم المستفيد فقط بلا فتح سجلات القسم", "شاشة 10"],
            ["الوصل والسند", "قيود اليومية", "القبض فوراً والصرف بعد توقيعين", "شاشات 11 و10 إلى 8"],
            ["الشعار والختم", "المؤسسة", "شهادات لشاشة 5 ووصولات لشاشة 3", "شاشتا 5 و3"],
        ],
        [300, 280, 680, 340],
        44,
    )
    svg.label(1652, y + 32, "قيود اليومية وسجل التدقيق إلحاقيان: الشاشات تعرضهما بلا تعديل ولا حذف.", 13, 400, MUTED)
    svg.h = int(y + 64)
    return svg


def main() -> None:
    ROOT.mkdir(parents=True, exist_ok=True)
    files = {
        "01-interfaces.svg": interfaces(),
        "02-permissions.svg": permissions(),
        "03-database.svg": database(),
    }
    for name, drawing in files.items():
        path = ROOT / name
        path.write_text(drawing.render(), encoding="utf-8")
        print(name, path.stat().st_size, drawing.w, drawing.h)


if __name__ == "__main__":
    main()
