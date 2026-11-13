package uz.simulator.phonetrade

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.runtime.getValue
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import uz.simulator.phonetrade.ui.screens.LoginScreen
import uz.simulator.phonetrade.ui.screens.MainScreen
import uz.simulator.phonetrade.ui.theme.PhoneTradeTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()

        val app = application as PhoneTradeApp

        setContent {
            PhoneTradeTheme {
                val token by app.session.token.collectAsStateWithLifecycle()

                if (token == null) {
                    LoginScreen(app)
                } else {
                    MainScreen(app)
                }
            }
        }
    }
}
