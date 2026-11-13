package uz.simulator.phonetrade.ui

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.navigationBarsPadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.draw.shadow
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.SolidColor
import androidx.compose.ui.graphics.StrokeCap
import androidx.compose.ui.graphics.StrokeJoin
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.graphics.vector.PathParser
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.selected
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import uz.simulator.phonetrade.ui.theme.Palette

/** Dizayndagi chiziqli ikonkalar (24 lik maydon, 1.8 qalinlik) — web va iOS bilan bir xil shakllar. */
object NavIcons {
    val Scan = stroke("M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 8v8M11 8v8M15 8v8")
    val Calendar = stroke("M4 5h16v15H4zM8 3v4M16 3v4M4 10h16")
    val Box = stroke("M21 8l-9-5-9 5v8l9 5 9-5zM3 8l9 5 9-5M12 13v8")
    val Person = stroke("M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM4 21c0-4 4-6 8-6s8 2 8 6")

    private fun stroke(path: String): ImageVector =
        ImageVector.Builder(defaultWidth = 24.dp, defaultHeight = 24.dp, viewportWidth = 24f, viewportHeight = 24f)
            .addPath(
                pathData = PathParser().parsePathString(path).toNodes(),
                stroke = SolidColor(Color.Black),
                strokeLineWidth = 1.8f,
                strokeLineCap = StrokeCap.Round,
                strokeLineJoin = StrokeJoin.Round,
            )
            .build()
}

data class GlassTab(val label: String, val icon: ImageVector)

/**
 * Pastki menyu: ekran ustida suzib turadigan shisha kapsula. Faol bo'lim oq kapsula ichida.
 */
@Composable
fun GlassTabBar(tabs: List<GlassTab>, selected: Int, onSelect: (Int) -> Unit, modifier: Modifier = Modifier) {
    Row(
        modifier = modifier
            .navigationBarsPadding()
            .padding(start = 16.dp, end = 16.dp, bottom = 12.dp, top = 4.dp)
            .fillMaxWidth()
            .shadow(14.dp, CircleShape, ambientColor = Color(0x331C2340), spotColor = Color(0x331C2340))
            .clip(CircleShape)
            .background(Color(0xD9FFFFFF))
            .border(1.dp, Color(0xE6FFFFFF), CircleShape)
            .padding(6.dp),
        horizontalArrangement = Arrangement.spacedBy(4.dp),
    ) {
        tabs.forEachIndexed { index, tab ->
            val active = index == selected

            Column(
                modifier = Modifier
                    .weight(1f)
                    .heightIn(min = 56.dp)
                    .then(if (active) Modifier.shadow(4.dp, CircleShape, spotColor = Color(0x221C2340)) else Modifier)
                    .clip(CircleShape)
                    .background(if (active) Color.White else Color.Transparent)
                    .clickable(role = Role.Tab) { onSelect(index) }
                    .semantics { this.selected = active },
                horizontalAlignment = Alignment.CenterHorizontally,
                verticalArrangement = Arrangement.spacedBy(2.dp, Alignment.CenterVertically),
            ) {
                Icon(tab.icon, contentDescription = null, modifier = Modifier.size(22.dp), tint = if (active) Palette.Primary else Palette.NavInk)
                Text(
                    tab.label,
                    fontSize = 11.sp,
                    fontWeight = if (active) FontWeight.ExtraBold else FontWeight.SemiBold,
                    color = if (active) Palette.Primary else Palette.NavInk,
                    maxLines = 1,
                )
            }
        }
    }
}

/** Filtr kapsulalari: faol — ko'k, qolganlari shisha. */
@Composable
fun <T> PillFilters(options: List<Pair<T, String>>, selected: T, onSelect: (T) -> Unit, modifier: Modifier = Modifier) {
    Row(modifier, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
        options.forEach { (value, label) ->
            val active = value == selected

            Text(
                text = label,
                modifier = Modifier
                    .heightIn(min = 44.dp)
                    .clip(CircleShape)
                    .background(if (active) Palette.Primary else Color(0xB3FFFFFF))
                    .then(if (active) Modifier else Modifier.border(1.dp, Color.White, CircleShape))
                    .clickable(role = Role.Tab) { onSelect(value) }
                    .semantics { this.selected = active }
                    .padding(horizontal = 16.dp, vertical = 12.dp),
                fontSize = 14.sp,
                fontWeight = if (active) FontWeight.Bold else FontWeight.SemiBold,
                color = if (active) Color.White else Palette.Ink,
                maxLines = 1,
            )
        }
    }
}
