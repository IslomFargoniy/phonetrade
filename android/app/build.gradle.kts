import java.io.FileInputStream
import java.util.Properties

plugins {
    alias(libs.plugins.android.application)
    alias(libs.plugins.kotlin.android)
    alias(libs.plugins.kotlin.compose)
}

// Release imzosi: android/keystore.properties (gitga qo'shilmaydi) — storeFile, storePassword, keyAlias, keyPassword.
val keystoreProps = Properties().apply {
    val file = File(rootDir, "keystore.properties")
    if (file.exists()) load(FileInputStream(file))
}
val hasReleaseKeystore = keystoreProps.getProperty("storeFile") != null

android {
    namespace = "uz.simulator.phonetrade"
    compileSdk = 36

    defaultConfig {
        applicationId = "uz.simulator.phonetrade"
        minSdk = 26
        targetSdk = 36
        versionCode = 2
        versionName = "1.0.1"

        // Lokal serverda sinash: ./gradlew installDebug -PapiUrl=http://10.0.2.2:8000/api/v1/
        val apiUrl = (project.findProperty("apiUrl") as String?) ?: "https://phonetrade.simulator.uz/api/v1/"
        buildConfigField("String", "API_URL", "\"$apiUrl\"")
    }

    signingConfigs {
        if (hasReleaseKeystore) {
            create("release") {
                storeFile = file(keystoreProps.getProperty("storeFile"))
                storePassword = keystoreProps.getProperty("storePassword")
                keyAlias = keystoreProps.getProperty("keyAlias")
                keyPassword = keystoreProps.getProperty("keyPassword")
            }
        }
    }

    buildTypes {
        debug {
            // Release'dagi kod siqish (R8) ilovani buzmasligini lokal serverda sinash uchun:
            // ./gradlew installDebug -PminifyDebug -PapiUrl=http://10.0.2.2:8000/api/v1/
            if (project.hasProperty("minifyDebug")) {
                isMinifyEnabled = true
                proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
            }
        }
        release {
            isMinifyEnabled = true
            isShrinkResources = true
            proguardFiles(getDefaultProguardFile("proguard-android-optimize.txt"), "proguard-rules.pro")
            signingConfig = if (hasReleaseKeystore) signingConfigs.getByName("release") else null
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions {
        jvmTarget = "17"
    }
    buildFeatures {
        compose = true
        buildConfig = true
    }
    packaging {
        resources {
            excludes += "/META-INF/{AL2.0,LGPL2.1}"
        }
    }
}

dependencies {
    implementation(libs.androidx.core.ktx)
    implementation(libs.androidx.lifecycle.runtime.ktx)
    implementation(libs.androidx.lifecycle.viewmodel.compose)
    implementation(libs.androidx.lifecycle.runtime.compose)
    implementation(libs.androidx.activity.compose)
    implementation(platform(libs.androidx.compose.bom))
    implementation(libs.androidx.ui)
    implementation(libs.androidx.ui.graphics)
    implementation(libs.androidx.ui.tooling.preview)
    implementation(libs.androidx.material3)
    implementation(libs.androidx.material.icons.extended)
    implementation(libs.androidx.security.crypto)
    implementation(libs.androidx.exifinterface)

    implementation(libs.retrofit)
    implementation(libs.retrofit.converter.gson)
    implementation(libs.okhttp)
    implementation(libs.okhttp.logging.interceptor)
    implementation(libs.kotlinx.coroutines.android)

    implementation(libs.androidx.camera.core)
    implementation(libs.androidx.camera.camera2)
    implementation(libs.androidx.camera.lifecycle)
    implementation(libs.androidx.camera.view)
    implementation(libs.mlkit.barcode.scanning)

    testImplementation(libs.junit)
}
