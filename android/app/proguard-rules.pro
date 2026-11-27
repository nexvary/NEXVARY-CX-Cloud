# NEXVARY CX — ProGuard rules
# Keep linphone SDK
-keep class org.linphone.** { *; }
-keep class org.linphone.core.** { *; }

# Keep serialization
-keepattributes *Annotation*, InnerClasses
-dontnote kotlinx.serialization.AnnotationsKt
-keepclassmembers class kotlinx.serialization.json.** { *** Companion; }
-keepclasseswithmembers class * { @kotlinx.serialization.Serializable <fields>; }

# OkHttp
-dontwarn okhttp3.**
-dontwarn okio.**
