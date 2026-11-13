package uz.simulator.phonetrade.ui.screens

import android.os.Build
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.imePadding
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.safeDrawingPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.OutlinedTextField
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import kotlinx.coroutines.launch
import uz.simulator.phonetrade.PhoneTradeApp
import uz.simulator.phonetrade.data.ApiException
import uz.simulator.phonetrade.data.LoginRequest
import uz.simulator.phonetrade.ui.AppField
import uz.simulator.phonetrade.ui.ErrorBanner
import uz.simulator.phonetrade.ui.GlassCard
import uz.simulator.phonetrade.ui.PrimaryButton
import uz.simulator.phonetrade.ui.theme.Palette

@Composable
fun LoginScreen(app: PhoneTradeApp) {
    val scope = rememberCoroutineScope()
    var phone by rememberSaveable { mutableStateOf("+998") }
    var password by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    fun submit() {
        loading = true
        error = null

        scope.launch {
            try {
                val profile = app.client.call {
                    login(LoginRequest(phone, password, "${Build.MANUFACTURER} ${Build.MODEL}".take(100)))
                }
                app.session.signIn(profile)
            } catch (exception: ApiException) {
                error = exception.message
            } finally {
                loading = false
            }
        }
    }

    Column(
        modifier = Modifier
            .fillMaxSize()
            .safeDrawingPadding()
            .imePadding()
            .verticalScroll(rememberScrollState())
            .padding(20.dp),
        verticalArrangement = Arrangement.Center,
    ) {
        Text("PhoneTrade", fontSize = 34.sp, fontWeight = FontWeight.ExtraBold, color = Palette.Ink)
        Text("Telefon do'koni savdo tizimi", fontSize = 15.sp, color = Palette.InkMid)
        Spacer(Modifier.height(28.dp))

        GlassCard {
            Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                AppField(
                    value = phone,
                    onChange = { phone = it },
                    label = "Telefon raqam",
                    keyboard = KeyboardType.Phone,
                )
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    label = { Text("Parol") },
                    singleLine = true,
                    modifier = Modifier.fillMaxWidth(),
                    visualTransformation = PasswordVisualTransformation(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                    shape = RoundedCornerShape(16.dp),
                )
                ErrorBanner(error)
                PrimaryButton(
                    text = "Kirish",
                    onClick = ::submit,
                    enabled = phone.count { it.isDigit() } >= 9 && password.isNotEmpty(),
                    loading = loading,
                )
            }
        }

        Spacer(Modifier.height(16.dp))
        Text(
            "Akkauntni do'kon egasi yaratadi. Parolni unutgan bo'lsangiz, egasiga murojaat qiling.",
            fontSize = 13.sp,
            color = Palette.InkSoft,
        )
    }
}
