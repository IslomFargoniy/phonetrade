package uz.simulator.phonetrade.ui.screens

import android.Manifest
import android.os.Build
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.Spacer
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.height
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.statusBarsPadding
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.OutlinedTextField
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import uz.simulator.phonetrade.data.PasswordBody
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.CalendarMonth
import androidx.compose.material.icons.filled.Inventory2
import androidx.compose.material.icons.filled.People
import androidx.compose.material.icons.filled.ShoppingCart
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.ModalBottomSheet
import androidx.compose.material3.NavigationBar
import androidx.compose.material3.NavigationBarItem
import androidx.compose.material3.OutlinedButton
import androidx.compose.material3.RadioButton
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.LaunchedEffect
import androidx.compose.runtime.getValue
import androidx.compose.runtime.key
import androidx.compose.runtime.mutableIntStateOf
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.rememberCoroutineScope
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import kotlinx.coroutines.launch
import uz.simulator.phonetrade.PhoneTradeApp
import uz.simulator.phonetrade.data.ApiException
import uz.simulator.phonetrade.data.Profile
import uz.simulator.phonetrade.print.PrinterDevice
import uz.simulator.phonetrade.print.ReceiptPrinter
import uz.simulator.phonetrade.ui.ErrorBanner
import uz.simulator.phonetrade.ui.LoadingBox
import uz.simulator.phonetrade.ui.PrimaryButton
import uz.simulator.phonetrade.ui.SectionTitle
import uz.simulator.phonetrade.ui.Segmented
import uz.simulator.phonetrade.ui.formatPhone
import uz.simulator.phonetrade.ui.grouped
import uz.simulator.phonetrade.ui.plainAmount
import uz.simulator.phonetrade.ui.sale.SaleScreen
import uz.simulator.phonetrade.ui.theme.Palette

private enum class Tab(val label: String) {
    Sale("Sotuv"),
    Installments("Nasiya"),
    Stock("Ombor"),
    Customers("Mijozlar"),
}

@Composable
fun MainScreen(app: PhoneTradeApp) {
    val scope = rememberCoroutineScope()
    val profile by app.session.profile.collectAsStateWithLifecycle()
    var error by remember { mutableStateOf<String?>(null) }
    var attempt by remember { mutableIntStateOf(0) }

    // Ilova ochilganda profil yangilanadi: kurs, filiallar, nasiya foizlari.
    LaunchedEffect(attempt) {
        try {
            app.session.setProfile(app.client.call { me() })
            error = null
        } catch (exception: ApiException) {
            error = exception.message
        }
    }

    val current = profile

    if (current == null) {
        Column(Modifier.fillMaxSize().padding(24.dp), verticalArrangement = Arrangement.Center) {
            if (error == null) {
                LoadingBox()
            } else {
                ErrorBanner(error)
                Spacer(Modifier.height(12.dp))
                PrimaryButton("Qayta urinish", { attempt++ })
                TextButton(onClick = { app.session.signOut() }, modifier = Modifier.align(Alignment.CenterHorizontally)) {
                    Text("Chiqish")
                }
            }
        }
        return
    }

    Shell(
        app = app,
        profile = current,
        onBranch = { id ->
            app.session.branchId = id
            scope.launch {
                runCatching { app.session.setProfile(app.client.call { me() }) }
            }
        },
    )
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun Shell(app: PhoneTradeApp, profile: Profile, onBranch: (Int) -> Unit) {
    var tab by rememberSaveable { mutableStateOf(Tab.Sale) }
    var showProfile by remember { mutableStateOf(false) }
    val branchId = profile.store.branch?.id ?: 0

    if (showProfile) {
        ModalBottomSheet(onDismissRequest = { showProfile = false }, containerColor = Palette.Background) {
            ProfileSheet(app, profile, onBranch)
        }
    }

    Scaffold(
        containerColor = Color.Transparent,
        topBar = {
            Row(
                Modifier.fillMaxWidth().statusBarsPadding().padding(start = 16.dp, end = 4.dp, top = 8.dp, bottom = 4.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Column(Modifier.weight(1f)) {
                    Text(tab.label, fontSize = 26.sp, fontWeight = FontWeight.ExtraBold, color = Palette.Ink)
                    Text(
                        listOfNotNull(
                            profile.store.branch?.name,
                            profile.store.rate?.let { "1 $ = ${grouped(plainAmount(it))} so'm" } ?: "Kurs kiritilmagan",
                        ).joinToString(" · "),
                        fontSize = 13.sp,
                        color = if (profile.store.rate == null) Palette.Danger else Palette.InkMid,
                    )
                }
                IconButton(onClick = { showProfile = true }) {
                    Icon(Icons.Default.AccountCircle, contentDescription = "Profil", tint = Palette.Primary)
                }
            }
        },
        bottomBar = {
            NavigationBar(containerColor = Palette.Glass) {
                Tab.entries.forEach { item ->
                    NavigationBarItem(
                        selected = tab == item,
                        onClick = { tab = item },
                        icon = {
                            Icon(
                                when (item) {
                                    Tab.Sale -> Icons.Default.ShoppingCart
                                    Tab.Installments -> Icons.Default.CalendarMonth
                                    Tab.Stock -> Icons.Default.Inventory2
                                    Tab.Customers -> Icons.Default.People
                                },
                                contentDescription = null,
                            )
                        },
                        label = { Text(item.label, fontWeight = FontWeight.SemiBold) },
                    )
                }
            }
        },
    ) { padding ->
        Box(Modifier.fillMaxSize().padding(padding)) {
            if (profile.store.branch == null) {
                ErrorBanner("Sizga filial biriktirilmagan. Do'kon egasiga murojaat qiling.", Modifier.padding(16.dp))
            } else {
                // Filial almashganda ekranlar noldan ochiladi: savat va ro'yxatlar filialga tegishli.
                key(branchId) {
                    when (tab) {
                        Tab.Sale -> SaleScreen(app, branchId)
                        Tab.Installments -> InstallmentsScreen(app)
                        Tab.Stock -> StockScreen(app)
                        Tab.Customers -> CustomersScreen(app)
                    }
                }
            }
        }
    }
}

@Composable
private fun ProfileSheet(app: PhoneTradeApp, profile: Profile, onBranch: (Int) -> Unit) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var printers by remember { mutableStateOf(ReceiptPrinter.paired(context)) }
    var printer by remember { mutableStateOf(app.session.printerAddress) }
    var columns by remember { mutableIntStateOf(app.session.printerColumns) }
    var bluetoothAllowed by remember { mutableStateOf(ReceiptPrinter.hasPermission(context)) }
    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        bluetoothAllowed = granted
        printers = ReceiptPrinter.paired(context)
    }

    Column(
        Modifier.fillMaxWidth().verticalScroll(rememberScrollState()).padding(horizontal = 20.dp).padding(bottom = 32.dp),
        verticalArrangement = Arrangement.spacedBy(10.dp),
    ) {
        Text(profile.user.name, fontSize = 22.sp, fontWeight = FontWeight.ExtraBold)
        Text(
            "${formatPhone(profile.user.phone)} · ${if (profile.user.isOwner) "Do'kon egasi" else "Sotuvchi"} · ${profile.store.name.orEmpty()}",
            color = Palette.InkMid,
            fontSize = 14.sp,
        )

        val branches = profile.store.branches.orEmpty()
        if (branches.size > 1) {
            SectionTitle("Filial", Modifier.padding(top = 8.dp))
            branches.forEach { branch ->
                ChoiceRow(branch.name, selected = branch.id == profile.store.branch?.id) { onBranch(branch.id) }
            }
        }

        SectionTitle("Chek printeri (Bluetooth)", Modifier.padding(top = 8.dp))
        if (!bluetoothAllowed) {
            OutlinedButton(onClick = {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) permission.launch(Manifest.permission.BLUETOOTH_CONNECT)
            }) { Text("Bluetooth'ga ruxsat berish") }
        } else if (printers.isEmpty()) {
            Text(
                "Juftlangan qurilma yo'q. Printerni telefon sozlamalaridagi Bluetooth bo'limida juftlang, keyin shu oynani qayta oching.",
                color = Palette.InkMid,
                fontSize = 14.sp,
            )
        } else {
            printers.forEach { device: PrinterDevice ->
                ChoiceRow(device.name, selected = device.address == printer) {
                    printer = device.address
                    app.session.printerAddress = device.address
                }
            }
            Segmented(
                options = listOf(32 to "58 mm qog'oz", 48 to "80 mm qog'oz"),
                selected = columns,
                onSelect = {
                    columns = it
                    app.session.printerColumns = it
                },
            )
        }

        Spacer(Modifier.height(8.dp))
        DeleteAccountButton(app, profile)
        OutlinedButton(
            onClick = {
                scope.launch {
                    // Server tokenni bekor qiladi; aloqa bo'lmasa ham qurilmadan o'chiriladi.
                    runCatching { app.client.call { logout() } }
                    app.session.signOut()
                }
            },
            modifier = Modifier.fillMaxWidth().height(50.dp),
        ) { Text("Chiqish", color = Palette.Danger, fontWeight = FontWeight.Bold) }
    }
}

@Composable
private fun ChoiceRow(label: String, selected: Boolean, onClick: () -> Unit) {
    Row(Modifier.fillMaxWidth().clickable(onClick = onClick), verticalAlignment = Alignment.CenterVertically) {
        RadioButton(selected = selected, onClick = onClick)
        Text(label, fontWeight = if (selected) FontWeight.Bold else FontWeight.Normal)
    }
}

/**
 * Akkauntni o'chirish (ilova do'konlari talabi). Sotuvchi darhol o'chiriladi;
 * do'kon egasi butun do'konni o'chirish so'rovini yuboradi.
 */
@Composable
private fun DeleteAccountButton(app: PhoneTradeApp, profile: Profile) {
    val scope = rememberCoroutineScope()
    var open by remember { mutableStateOf(false) }
    var password by remember { mutableStateOf("") }
    var loading by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var done by remember { mutableStateOf<String?>(null) }
    val owner = profile.user.isOwner

    TextButton(onClick = { open = true }) {
        Text(if (owner) "Do'konni o'chirish" else "Akkauntni o'chirish", color = Palette.Danger)
    }
    done?.let { Text(it, color = Palette.InkMid, fontSize = 14.sp) }

    if (!open) return

    AlertDialog(
        onDismissRequest = { if (!loading) open = false },
        title = { Text(if (owner) "Do'konni o'chirish" else "Akkauntni o'chirish") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text(
                    if (owner) {
                        "Butun do'konni — xodimlar, mijozlar, pasport rasmlari, ombor va savdo yozuvlari bilan — " +
                            "o'chirish so'rovi yuboriladi. 30 kun ichida bajariladi; web paneldagi Sozlamalar → Profil " +
                            "bo'limida bekor qilishingiz mumkin."
                    } else {
                        "Akkauntingiz darhol o'chiriladi: ism, telefon va parol tozalanadi. " +
                            "Siz qilgan savdolar do'kon hisobida shaxsiy ma'lumotsiz qoladi."
                    },
                    fontSize = 14.sp,
                )
                OutlinedTextField(
                    value = password,
                    onValueChange = { password = it },
                    label = { Text("Parolingiz") },
                    singleLine = true,
                    visualTransformation = PasswordVisualTransformation(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                )
                ErrorBanner(error)
            }
        },
        confirmButton = {
            TextButton(
                enabled = password.isNotEmpty() && !loading,
                onClick = {
                    loading = true
                    error = null
                    scope.launch {
                        try {
                            val result = app.client.call { deleteAccount(PasswordBody(password)) }
                            open = false
                            password = ""

                            if (result.status == "deleted") {
                                app.session.signOut()
                            } else {
                                done = result.message
                            }
                        } catch (exception: ApiException) {
                            error = exception.message
                        } finally {
                            loading = false
                        }
                    }
                },
            ) { Text(if (owner) "So'rov yuborish" else "O'chirish", color = Palette.Danger) }
        },
        dismissButton = { TextButton(onClick = { open = false }, enabled = !loading) { Text("Bekor qilish") } },
    )
}

