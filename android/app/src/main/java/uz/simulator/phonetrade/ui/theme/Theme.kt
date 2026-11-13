package uz.simulator.phonetrade.ui.theme

import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Shapes
import androidx.compose.material3.lightColorScheme
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.graphics.Brush
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp

/* Liquid Glass dizayni: web ilovadagi ranglar bilan bir xil. */
object Palette {
    val Background = Color(0xFFE9EDF5)
    val Primary = Color(0xFF0058C2)
    val Ink = Color(0xFF1C1C1E)
    val InkMid = Color(0xFF5B6275)
    val InkSoft = Color(0xFF8A90A2)
    val Glass = Color(0xB8FFFFFF)
    val GlassInner = Color(0x8CFFFFFF)
    val Stroke = Color(0xCCFFFFFF)
    val Success = Color(0xFF12805C)
    val SuccessBg = Color(0x2212805C)
    val Danger = Color(0xFFC62828)
    val DangerBg = Color(0x1FC62828)
    val Warning = Color(0xFFB26A00)
    val WarningBg = Color(0x22B26A00)
    val PrimaryBg = Color(0x1F0058C2)
}

private val colors = lightColorScheme(
    primary = Palette.Primary,
    onPrimary = Color.White,
    primaryContainer = Palette.PrimaryBg,
    onPrimaryContainer = Palette.Primary,
    secondaryContainer = Palette.PrimaryBg,
    onSecondaryContainer = Palette.Primary,
    background = Palette.Background,
    onBackground = Palette.Ink,
    surface = Color.White,
    onSurface = Palette.Ink,
    surfaceVariant = Color(0xFFF1F3F9),
    onSurfaceVariant = Palette.InkMid,
    surfaceContainer = Color.White,
    surfaceContainerLow = Color.White,
    surfaceContainerHigh = Color.White,
    surfaceContainerHighest = Color(0xFFF1F3F9),
    outline = Color(0xFFC9CEDB),
    outlineVariant = Color(0xFFE1E5EE),
    error = Palette.Danger,
)

private val shapes = Shapes(
    extraSmall = RoundedCornerShape(10.dp),
    small = RoundedCornerShape(14.dp),
    medium = RoundedCornerShape(18.dp),
    large = RoundedCornerShape(24.dp),
    extraLarge = RoundedCornerShape(28.dp),
)

@Composable
fun PhoneTradeTheme(content: @Composable () -> Unit) {
    MaterialTheme(colorScheme = colors, shapes = shapes) {
        Box(Modifier.fillMaxSize().background(Palette.Background).background(blobs)) {
            content()
        }
    }
}

/** Orqa fondagi rangli dog'lar: shisha panellar ostidan ko'rinib turadi. */
private val blobs = Brush.linearGradient(
    colors = listOf(Color(0x5590B8FF), Color(0x00E9EDF5), Color(0x44FFC2DC), Color(0x44A8F0D8)),
    start = Offset(0f, 0f),
    end = Offset(900f, 2200f),
)
