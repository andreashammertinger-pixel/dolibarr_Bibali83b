plugins {
    id("com.android.application")
    id("org.jetbrains.kotlin.android")
}

android {
    namespace = "com.smartimmo.ideenbox"
    compileSdk = 35

    defaultConfig {
        applicationId = "com.smartimmo.ideenbox"
        minSdk = 26
        targetSdk = 35
        versionCode = 1
        versionName = "1.0.0"
    }

    // Signatur für Updates: Schlüssel über Umgebungsvariablen (siehe README), sonst Debug-Schlüssel
    signingConfigs {
        create("release") {
            val ks = System.getenv("IDEENBOX_KEYSTORE")
            if (ks != null && file(ks).exists()) {
                storeFile = file(ks)
                storePassword = System.getenv("IDEENBOX_KEYSTORE_PASSWORD")
                keyAlias = System.getenv("IDEENBOX_KEY_ALIAS") ?: "ideenbox"
                keyPassword = System.getenv("IDEENBOX_KEY_PASSWORD") ?: System.getenv("IDEENBOX_KEYSTORE_PASSWORD")
            } else {
                initWith(getByName("debug"))
            }
        }
    }

    buildTypes {
        release {
            isMinifyEnabled = false
            signingConfig = signingConfigs.getByName("release")
        }
    }
    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }
    kotlinOptions { jvmTarget = "17" }
    testOptions {
        unitTests.isReturnDefaultValues = true
        unitTests.isIncludeAndroidResources = true
        // optional anderer Maven-Spiegel für Robolectric (z. B. in abgeschotteten Build-Umgebungen)
        unitTests.all { t -> System.getenv("ROBOLECTRIC_REPO")?.let { t.systemProperty("robolectric.dependency.repo.url", it) } }
    }
}

dependencies {
    implementation("androidx.core:core-ktx:1.13.1")
    implementation("androidx.work:work-runtime-ktx:2.9.1")
    testImplementation("junit:junit:4.13.2")
    testImplementation("org.json:json:20240303")
    testImplementation("org.robolectric:robolectric:4.14.1")
    testImplementation("androidx.test:core:1.6.1")
    testImplementation("androidx.work:work-testing:2.9.1")
}
